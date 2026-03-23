<?php

namespace App\Services\DepedWorkbook;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

interface DepedWorkbookInspector
{
    public function inspect(Spreadsheet $spreadsheet): ?array;
}
