<?php

namespace App\Libraries;

use Generator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

final class AdmissionWorkbookReader
{
    private const MAIN_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const MAX_SHEET_BYTES = 268435456;
    private const MAX_SHARED_STRINGS_BYTES = 67108864;

    /** @var array{worksheetName: string, totalRows: int, totalColumns: int} */
    private array $info;
    private bool $xlsx;
    private ?string $worksheetEntry = null;
    private ?string $sharedStringsEntry = null;

    public function __construct(private readonly string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('The queued workbook file is missing or unreadable.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setIgnoreRowsWithNoCells(true);
        $worksheets = $reader->listWorksheetInfo($path);
        if ($worksheets === []) {
            throw new RuntimeException('The workbook does not contain a worksheet.');
        }

        $first = $worksheets[0];
        $this->info = [
            'worksheetName' => (string) $first['worksheetName'],
            'totalRows' => (int) $first['totalRows'],
            'totalColumns' => (int) $first['totalColumns'],
        ];
        $this->xlsx = strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx';

        if ($this->xlsx) {
            $this->inspectXlsxPackage();
        }
    }

    /** @return array{worksheetName: string, totalRows: int, totalColumns: int} */
    public function worksheetInfo(): array
    {
        return $this->info;
    }

    /** @return Generator<int, array<int, mixed>> */
    public function rows(int $startRow = 1): Generator
    {
        if ($this->xlsx) {
            yield from $this->xlsxRows(max(1, $startRow));

            return;
        }

        yield from $this->legacyRows(max(1, $startRow));
    }

    private function inspectXlsxPackage(): void
    {
        $zip = new ZipArchive();
        if ($zip->open($this->path) !== true) {
            throw new RuntimeException('The XLSX package could not be opened.');
        }

        try {
            $this->worksheetEntry = $this->firstWorksheetEntry($zip);
            $this->assertEntrySize($zip, $this->worksheetEntry, self::MAX_SHEET_BYTES, 'worksheet');

            $sharedIndex = $zip->locateName('xl/sharedStrings.xml', ZipArchive::FL_NOCASE);
            if ($sharedIndex !== false) {
                $this->sharedStringsEntry = (string) $zip->getNameIndex($sharedIndex);
                $this->assertEntrySize($zip, $this->sharedStringsEntry, self::MAX_SHARED_STRINGS_BYTES, 'shared strings');
            }
        } finally {
            $zip->close();
        }
    }

    /** @return Generator<int, array<int, mixed>> */
    private function xlsxRows(int $startRow): Generator
    {
        if ($this->worksheetEntry === null) {
            throw new RuntimeException('The first XLSX worksheet could not be located.');
        }

        $sharedStrings = $this->readSharedStrings();
        $reader = $this->openEntry($this->worksheetEntry);
        $fallbackRow = 0;

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }

                $fallbackRow++;
                $rowNumber = (int) ($reader->getAttribute('r') ?: $fallbackRow);
                if ($rowNumber < $startRow) {
                    continue;
                }

                $xml = $reader->readOuterXml();
                if ($xml === '') {
                    continue;
                }

                yield $rowNumber => $this->parseRow($xml, $sharedStrings);
            }
        } finally {
            $reader->close();
            unset($sharedStrings);
        }
    }

    /** @return Generator<int, array<int, mixed>> */
    private function legacyRows(int $startRow): Generator
    {
        $reader = IOFactory::createReaderForFile($this->path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $reader->setIgnoreRowsWithNoCells(true);
        $reader->setLoadSheetsOnly($this->info['worksheetName']);
        $book = $reader->load($this->path);

        try {
            $sheet = $book->getSheet(0);
            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($this->info['totalColumns']);
            for ($rowNumber = $startRow; $rowNumber <= $this->info['totalRows']; $rowNumber++) {
                $row = $sheet->rangeToArray("A{$rowNumber}:{$lastColumn}{$rowNumber}", null, true, true, false)[0] ?? [];
                yield $rowNumber => $row;
            }
        } finally {
            $book->disconnectWorksheets();
        }
    }

    /** @return list<string> */
    private function readSharedStrings(): array
    {
        if ($this->sharedStringsEntry === null) {
            return [];
        }

        $strings = [];
        $reader = $this->openEntry($this->sharedStringsEntry);
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                    continue;
                }
                $xml = $reader->readOuterXml();
                $node = $this->parseXml($xml, 'shared string');
                $strings[] = $this->nodeText($node);
            }
        } finally {
            $reader->close();
        }

        return $strings;
    }

    /** @param list<string> $sharedStrings @return array<int, mixed> */
    private function parseRow(string $xml, array $sharedStrings): array
    {
        $row = array_fill(0, max(1, $this->info['totalColumns']), null);
        $node = $this->parseXml($xml, 'worksheet row');
        $node->registerXPathNamespace('m', self::MAIN_NAMESPACE);
        $cells = $node->xpath('m:c') ?: [];

        foreach ($cells as $cell) {
            $reference = strtoupper((string) $cell['r']);
            if (preg_match('/^([A-Z]+)/', $reference, $match) !== 1) {
                continue;
            }
            $column = $this->columnIndex($match[1]);
            if ($column < 0) {
                continue;
            }
            if ($column >= count($row)) {
                $row = array_pad($row, $column + 1, null);
            }

            $cell->registerXPathNamespace('m', self::MAIN_NAMESPACE);
            $type = (string) $cell['t'];
            if ($type === 'inlineStr') {
                $row[$column] = $this->nodeText($cell);
                continue;
            }

            $values = $cell->xpath('m:v') ?: [];
            if ($values === []) {
                $row[$column] = null;
                continue;
            }
            $raw = (string) $values[0];
            $row[$column] = match ($type) {
                's' => $sharedStrings[(int) $raw] ?? '',
                'b' => $raw === '1',
                default => $raw,
            };
        }

        return $row;
    }

    private function nodeText(SimpleXMLElement $node): string
    {
        $node->registerXPathNamespace('m', self::MAIN_NAMESPACE);
        $parts = $node->xpath('.//m:t') ?: [];

        return implode('', array_map(static fn (SimpleXMLElement $part): string => (string) $part, $parts));
    }

    private function parseXml(string $xml, string $label): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $node = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $node instanceof SimpleXMLElement) {
            throw new RuntimeException('The XLSX ' . $label . ' XML is invalid.');
        }

        return $node;
    }

    private function openEntry(string $entry): XMLReader
    {
        $reader = new XMLReader();
        $uri = 'zip://' . str_replace('\\', '/', $this->path) . '#' . $entry;
        if (! $reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('The XLSX entry ' . $entry . ' could not be read.');
        }
        $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);

        return $reader;
    }

    private function firstWorksheetEntry(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (is_string($workbookXml) && is_string($relationshipsXml)) {
            $workbook = $this->parseXml($workbookXml, 'workbook');
            $sheets = $workbook->xpath('//*[local-name()="sheet"]') ?: [];
            if ($sheets !== []) {
                $relationshipId = '';
                foreach ($sheets[0]->getNamespaces(true) as $namespace) {
                    $attributes = $sheets[0]->attributes($namespace);
                    if (isset($attributes['id'])) {
                        $relationshipId = (string) $attributes['id'];
                        break;
                    }
                }

                if ($relationshipId !== '') {
                    $relationships = $this->parseXml($relationshipsXml, 'workbook relationships');
                    foreach ($relationships->xpath('//*[local-name()="Relationship"]') ?: [] as $relationship) {
                        if ((string) $relationship['Id'] !== $relationshipId) {
                            continue;
                        }
                        $target = str_replace('\\', '/', (string) $relationship['Target']);
                        $entry = str_starts_with($target, '/')
                            ? ltrim($target, '/')
                            : $this->normalizeEntryPath('xl/' . $target);
                        $index = $zip->locateName($entry, ZipArchive::FL_NOCASE);
                        if ($index !== false) {
                            return (string) $zip->getNameIndex($index);
                        }
                    }
                }
            }
        }

        $fallback = $zip->locateName('xl/worksheets/sheet1.xml', ZipArchive::FL_NOCASE);
        if ($fallback === false) {
            throw new RuntimeException('The first XLSX worksheet could not be located.');
        }

        return (string) $zip->getNameIndex($fallback);
    }

    private function normalizeEntryPath(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private function assertEntrySize(ZipArchive $zip, string $entry, int $maximum, string $label): void
    {
        $stats = $zip->statName($entry);
        if (! is_array($stats) || ! isset($stats['size'])) {
            throw new RuntimeException('The XLSX ' . $label . ' entry is missing.');
        }
        if ((int) $stats['size'] > $maximum) {
            throw new RuntimeException('The XLSX ' . $label . ' is too large to process safely.');
        }
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
