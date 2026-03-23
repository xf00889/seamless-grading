<?php

namespace App\Services\DepedWorkbook;

use App\Enums\DepedWorkbookDocumentType;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DepedSf1ShsParser extends AbstractDepedWorkbookInspector
{
    private const METADATA_CELLS = [
        'school_name' => 'I5',
        'school_id' => 'S5',
        'semester' => 'I9',
        'school_year' => 'S9',
        'section' => 'I16',
    ];

    private const ROW_COLUMNS = [
        'lrn' => 'A',
        'full_name' => 'C',
        'sex' => 'K',
        'birth_date' => 'L',
        'age' => 'O',
        'religion' => 'Q',
        'house_street' => 'U',
        'barangay' => 'Z',
        'municipality_city' => 'AE',
        'province' => 'AG',
        'father_name' => 'AK',
        'mother_name' => 'AP',
        'guardian_name' => 'AR',
        'guardian_relationship' => 'AV',
        'parent_guardian_contact_number' => 'AX',
        'learning_modality' => 'BA',
        'remarks' => 'BC',
    ];

    private const SUFFIXES = ['JR', 'SR', 'II', 'III', 'IV', 'V'];

    public function inspect(Spreadsheet $spreadsheet): ?array
    {
        $sheet = $this->locateSourceSheet($spreadsheet);

        if ($sheet === null) {
            return null;
        }

        $metadata = $this->extractMetadata($sheet);
        $rows = $this->extractRows($sheet);

        return [
            'document_type' => DepedWorkbookDocumentType::Sf1ShsRosterImport->value,
            'document_label' => DepedWorkbookDocumentType::Sf1ShsRosterImport->label(),
            'handling_mode' => DepedWorkbookDocumentType::Sf1ShsRosterImport->handlingMode(),
            'template_document_type_hint' => DepedWorkbookDocumentType::Sf1ShsRosterImport->templateDocumentTypeHint(),
            ...$this->workbookSummary($spreadsheet),
            'source_sheet_name' => $sheet->getTitle(),
            'metadata' => $metadata,
            'anchors' => [
                'title' => $this->cellDisplayValue($sheet, 'H3'),
                'school_name_label' => $this->cellDisplayValue($sheet, 'F5'),
                'school_id_label' => $this->cellDisplayValue($sheet, 'P5'),
                'semester_label' => $this->cellDisplayValue($sheet, 'G9'),
                'school_year_label' => $this->cellDisplayValue($sheet, 'P9'),
                'section_label' => $this->cellDisplayValue($sheet, 'G16'),
                'header_row_18' => Arr::only($this->rowSnapshot($sheet, 18), ['A', 'C', 'K', 'L', 'O', 'Q', 'U', 'AK', 'AR', 'AX', 'BA', 'BC']),
                'header_row_19' => Arr::only($this->rowSnapshot($sheet, 19), ['U', 'Z', 'AE', 'AG', 'AK', 'AP', 'AR', 'AV', 'BC']),
            ],
            'is_positional_template' => false,
            'rows' => $rows,
        ];
    }

    private function locateSourceSheet(Spreadsheet $spreadsheet): ?Worksheet
    {
        return $this->firstMatchingSheet($spreadsheet, function (Worksheet $sheet): bool {
            return $this->cellContains($sheet, 'H3', 'School Form 1 School Register for Senior High School')
                && $this->cellContains($sheet, 'F5', 'School Name')
                && $this->cellContains($sheet, 'P5', 'School ID')
                && $this->cellContains($sheet, 'G9', 'Semester')
                && $this->cellContains($sheet, 'P9', 'School Year')
                && $this->cellContains($sheet, 'G16', 'Section')
                && $this->cellContains($sheet, 'A18', 'LRN')
                && $this->cellContains($sheet, 'C18', 'NAME')
                && $this->cellContains($sheet, 'K18', 'SEX')
                && $this->cellContains($sheet, 'L18', 'BIRTH DATE')
                && $this->cellContains($sheet, 'BA18', 'Learning Modality')
                && $this->cellContains($sheet, 'BC18', 'REMARKS');
        });
    }

    private function extractMetadata(Worksheet $sheet): array
    {
        $metadata = [];

        foreach (self::METADATA_CELLS as $key => $coordinate) {
            $metadata[$key] = $this->cleanText($this->cellDisplayValue($sheet, $coordinate));
        }

        return $metadata;
    }

    private function extractRows(Worksheet $sheet): array
    {
        $rows = [];
        $blankRowStreak = 0;
        $highestRow = $sheet->getHighestDataRow();

        for ($row = 20; $row <= $highestRow; $row++) {
            if ($this->rowStartsLegend($sheet, $row)) {
                break;
            }

            $payload = $this->buildPayload($sheet, $row);

            if ($this->isClearlyOutsideDataset($payload)) {
                $blankRowStreak++;

                if ($rows !== [] && $blankRowStreak >= 2) {
                    break;
                }

                continue;
            }

            $blankRowStreak = 0;

            if ($this->isTotalRow($payload)) {
                continue;
            }

            $normalized = $this->normalizePayload($payload);

            if ($this->isClearlyOutsideDataset($normalized)) {
                continue;
            }

            $rows[] = [
                'row_number' => $row,
                'payload' => $payload,
                'normalized_data' => $normalized,
            ];
        }

        return $rows;
    }

    private function buildPayload(Worksheet $sheet, int $row): array
    {
        $payload = [];

        foreach (self::ROW_COLUMNS as $key => $column) {
            $payload[$key] = $this->cleanText($this->cellDisplayValue($sheet, $column.$row));
        }

        $payload['raw_name'] = $payload['full_name'];

        return $payload;
    }

    private function normalizePayload(array $payload): array
    {
        $nameParts = $this->parseName($payload['full_name'] ?? null);
        $birthDisplay = $payload['birth_date'] ?? null;

        return [
            'lrn' => $this->normalizeLrn($payload['lrn'] ?? null),
            'raw_name' => $payload['raw_name'] ?? null,
            'full_name' => $payload['full_name'] ?? null,
            'last_name' => $nameParts['last_name'],
            'first_name' => $nameParts['first_name'],
            'middle_name' => $nameParts['middle_name'],
            'suffix' => $nameParts['suffix'],
            'sex' => $this->normalizeSex($payload['sex'] ?? null)?->value,
            'birth_date' => $this->normalizeDate($birthDisplay, $birthDisplay),
            'age' => $this->normalizeAge($payload['age'] ?? null),
            'religion' => $this->cleanText($payload['religion'] ?? null),
            'house_street' => $this->cleanText($payload['house_street'] ?? null),
            'barangay' => $this->cleanText($payload['barangay'] ?? null),
            'municipality_city' => $this->cleanText($payload['municipality_city'] ?? null),
            'province' => $this->cleanText($payload['province'] ?? null),
            'father_name' => $this->cleanText($payload['father_name'] ?? null),
            'mother_name' => $this->cleanText($payload['mother_name'] ?? null),
            'guardian_name' => $this->cleanText($payload['guardian_name'] ?? null),
            'guardian_relationship' => $this->cleanText($payload['guardian_relationship'] ?? null),
            'parent_guardian_contact_number' => $this->cleanText($payload['parent_guardian_contact_number'] ?? null),
            'learning_modality' => $this->cleanText($payload['learning_modality'] ?? null),
            'remarks' => $this->cleanText($payload['remarks'] ?? null),
        ];
    }

    private function parseName(?string $fullName): array
    {
        $clean = $this->cleanText($fullName);

        if ($clean === null) {
            return [
                'last_name' => null,
                'first_name' => null,
                'middle_name' => null,
                'suffix' => null,
            ];
        }

        $segments = array_values(array_filter(array_map(
            fn ($segment) => trim($segment),
            preg_split('/\s*,\s*/', $clean),
        ), fn ($segment) => $segment !== ''));

        $lastName = $this->cleanName($segments[0] ?? null);
        $remainder = trim(implode(' ', array_slice($segments, 1)));

        if ($remainder === '') {
            return [
                'last_name' => $lastName,
                'first_name' => null,
                'middle_name' => null,
                'suffix' => null,
            ];
        }

        $tokens = array_values(array_filter(array_map(
            fn ($token) => trim($token, " \t\n\r\0\x0B-"),
            preg_split('/\s+/', $remainder),
        ), fn ($token) => $token !== ''));

        $firstName = $this->cleanName($tokens[0] ?? null);
        $middleName = null;
        $suffix = null;

        if (count($tokens) >= 2) {
            if ($this->isSuffixToken($tokens[1])) {
                $suffix = $this->cleanSuffix($tokens[1]);
                $middleName = $this->cleanName(implode(' ', array_slice($tokens, 2)));
            } elseif ($this->isSuffixToken($tokens[count($tokens) - 1])) {
                $suffix = $this->cleanSuffix($tokens[count($tokens) - 1]);
                $middleName = $this->cleanName(implode(' ', array_slice($tokens, 1, -1)));
            } else {
                $middleName = $this->cleanName(implode(' ', array_slice($tokens, 1)));
            }
        }

        return [
            'last_name' => $lastName,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'suffix' => $suffix,
        ];
    }

    private function isSuffixToken(string $token): bool
    {
        return in_array(Str::upper(rtrim(trim($token), '.')), self::SUFFIXES, true);
    }

    private function normalizeAge(mixed $value): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : (int) $digits;
    }

    private function isClearlyOutsideDataset(array $payload): bool
    {
        return collect([
            $payload['lrn'] ?? null,
            $payload['full_name'] ?? null,
            $payload['sex'] ?? null,
            $payload['birth_date'] ?? null,
            $payload['remarks'] ?? null,
        ])->every(fn ($value): bool => $this->cleanText($value) === null);
    }

    private function isTotalRow(array $payload): bool
    {
        $name = $this->normalizeText($payload['full_name'] ?? null);

        return str_contains($name, 'TOTAL MALE')
            || str_contains($name, 'TOTAL FEMALE')
            || str_contains($name, 'COMBINED');
    }

    private function rowStartsLegend(Worksheet $sheet, int $row): bool
    {
        $cells = $this->rowSnapshot($sheet, $row);
        $joined = $this->normalizeText(implode(' ', array_filter($cells)));

        return str_contains($joined, 'LEGEND: LIST AND CODE OF INDICATORS UNDER REMARKS COLUMN')
            || str_contains($joined, 'GENERATED ON:')
            || (
                str_contains($joined, 'INDICATOR')
                && str_contains($joined, 'REQUIRED INFORMATION')
            );
    }

    private function rowSnapshot(Worksheet $sheet, int $row): array
    {
        $snapshot = [];

        for ($column = 1; $column <= Coordinate::columnIndexFromString('BC'); $column++) {
            $letter = Coordinate::stringFromColumnIndex($column);
            $value = $this->cellDisplayValue($sheet, $letter.$row);

            if ($value !== null) {
                $snapshot[$letter] = $value;
            }
        }

        return $snapshot;
    }
}
