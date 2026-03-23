<?php

namespace App\Services\TeacherGradingSheet;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class SpreadsheetTargetResolver
{
    public function resolve(Spreadsheet $spreadsheet, string $target): array
    {
        $normalizedTarget = trim($target);

        if ($normalizedTarget === '') {
            throw new RuntimeException('Spreadsheet target cannot be empty.');
        }

        if (preg_match('/^[A-Z]{1,3}[1-9][0-9]{0,6}$/i', $normalizedTarget) === 1) {
            $worksheet = $spreadsheet->getActiveSheet();
            $cell = strtoupper($normalizedTarget);

            return $this->coordinatePayload($worksheet, $cell);
        }

        $namedRange = $spreadsheet->getNamedRange($normalizedTarget);

        if ($namedRange === null || $namedRange->getWorksheet() === null) {
            throw new RuntimeException('Unable to resolve the mapped spreadsheet target "'.$normalizedTarget.'".');
        }

        $cells = $namedRange->getCellsInRange();

        if ($cells === []) {
            throw new RuntimeException('The mapped spreadsheet target "'.$normalizedTarget.'" does not point to a valid cell.');
        }

        return $this->coordinatePayload($namedRange->getWorksheet(), strtoupper($cells[0]));
    }

    private function coordinatePayload(Worksheet $worksheet, string $cell): array
    {
        [$column, $row] = Coordinate::coordinateFromString($cell);

        return [
            'worksheet' => $worksheet,
            'cell' => $cell,
            'column' => $column,
            'row' => (int) $row,
        ];
    }
}
