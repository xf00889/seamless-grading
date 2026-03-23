<?php

namespace App\Services\DepedWorkbook;

use App\Enums\LearnerSex;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class AbstractDepedWorkbookInspector implements DepedWorkbookInspector
{
    protected function cellDisplayValue(Worksheet $sheet, string $coordinate): ?string
    {
        $value = trim((string) $sheet->getCell($coordinate)->getFormattedValue());

        return $value === '' ? null : preg_replace('/\s+/u', ' ', $value);
    }

    protected function cellRawValue(Worksheet $sheet, string $coordinate): mixed
    {
        return $sheet->getCell($coordinate)->getValue();
    }

    protected function normalizedCellValue(Worksheet $sheet, string $coordinate): string
    {
        return $this->normalizeText($this->cellDisplayValue($sheet, $coordinate));
    }

    protected function cellContains(Worksheet $sheet, string $coordinate, string $expected): bool
    {
        $value = $this->normalizedCellValue($sheet, $coordinate);

        return $value !== '' && str_contains($value, $this->normalizeText($expected));
    }

    protected function normalizeText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return Str::upper(Str::of((string) $value)->squish()->value());
    }

    protected function cleanText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = Str::of((string) $value)->squish()->trim();

        return $text->isEmpty() ? null : $text->value();
    }

    protected function cleanName(mixed $value): ?string
    {
        $text = $this->cleanText($value);

        return $text === null ? null : Str::of($text)->title()->value();
    }

    protected function cleanSuffix(mixed $value): ?string
    {
        $text = $this->cleanText($value);

        return $text === null ? null : Str::upper($text);
    }

    protected function normalizeLrn(mixed $value): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        $text = preg_replace('/\.0+$/', '', $text);
        $digits = preg_replace('/\D+/', '', $text);

        return $digits === '' ? null : $digits;
    }

    protected function normalizeSex(mixed $value): ?LearnerSex
    {
        return match ($this->normalizeText($value)) {
            'M', 'MALE' => LearnerSex::Male,
            'F', 'FEMALE' => LearnerSex::Female,
            default => null,
        };
    }

    protected function normalizeDate(mixed $rawValue, mixed $displayValue = null): ?string
    {
        $value = $rawValue;

        if ($value instanceof Cell) {
            $value = $value->getValue();
        }

        if ($value === null || trim((string) $value) === '') {
            $value = $displayValue;
        }

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function workbookSummary(Spreadsheet $spreadsheet): array
    {
        return [
            'sheet_count' => count($spreadsheet->getSheetNames()),
            'sheet_names' => $spreadsheet->getSheetNames(),
        ];
    }

    protected function sheetNamed(Spreadsheet $spreadsheet, string $expectedTitle): ?Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if ($this->normalizeText($sheet->getTitle()) === $this->normalizeText($expectedTitle)) {
                return $sheet;
            }
        }

        return null;
    }

    protected function firstMatchingSheet(Spreadsheet $spreadsheet, callable $matcher): ?Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if ($matcher($sheet) === true) {
                return $sheet;
            }
        }

        return null;
    }
}
