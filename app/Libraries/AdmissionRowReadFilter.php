<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

final class AdmissionRowReadFilter implements IReadFilter
{
    public function __construct(private int $startRow, private int $endRow, private bool $includeHeader = false)
    {
    }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return ($this->includeHeader && $row === 1) || ($row >= $this->startRow && $row <= $this->endRow);
    }
}
