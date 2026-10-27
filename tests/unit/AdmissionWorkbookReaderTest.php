<?php

namespace Tests\Unit;

use App\Libraries\AdmissionWorkbookReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

final class AdmissionWorkbookReaderTest extends TestCase
{
    private ?string $path = null;

    protected function tearDown(): void
    {
        if ($this->path !== null && is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testStreamsXlsxRowsWithoutChangingCellPositionsOrValues(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'dhe-reader-');
        self::assertNotFalse($temporary);
        $this->path = $temporary . '.xlsx';
        rename($temporary, $this->path);
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Admissions');
        $sheet->fromArray([
            ['Academic_Year', 'Univ_Code', 'College_Code', 'StudentID'],
            ['2024-25', 'U-1234', 'C-100', 'STU-001'],
            ['2024-25', 'U-1234', null, 'STU-002'],
        ]);
        (new Xlsx($book))->save($this->path);
        $book->disconnectWorksheets();

        $reader = new AdmissionWorkbookReader($this->path);
        $rows = iterator_to_array($reader->rows());

        self::assertSame([
            'worksheetName' => 'Admissions',
            'totalRows' => 3,
            'totalColumns' => 4,
        ], $reader->worksheetInfo());
        self::assertSame(['Academic_Year', 'Univ_Code', 'College_Code', 'StudentID'], $rows[1]);
        self::assertSame(['2024-25', 'U-1234', 'C-100', 'STU-001'], $rows[2]);
        self::assertSame(['2024-25', 'U-1234', null, 'STU-002'], $rows[3]);
    }
}
