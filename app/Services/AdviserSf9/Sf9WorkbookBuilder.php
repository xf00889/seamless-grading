<?php

namespace App\Services\AdviserSf9;

use App\Models\Template;
use App\Services\TeacherGradingSheet\SpreadsheetTargetResolver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class Sf9WorkbookBuilder
{
    public function __construct(
        private readonly SpreadsheetTargetResolver $targetResolver,
    ) {}

    public function build(array $previewData): string
    {
        /** @var Template|null $template */
        $template = $previewData['template']['model'] ?? null;

        if ($template === null) {
            throw new RuntimeException('No SF9 template is available for export.');
        }

        $templatePath = Storage::disk($template->file_disk)->path($template->file_path);
        $spreadsheet = IOFactory::load($templatePath);
        $fieldMaps = $template->fieldMaps->keyBy('field_key');

        $scalarValues = [
            'school_name' => $previewData['school_name'],
            'school_year_name' => $previewData['section']->schoolYear->name,
            'grading_period_label' => $previewData['gradingPeriod']->quarter->label(),
            'grade_level_name' => $previewData['section']->gradeLevel->name,
            'section_name' => $previewData['section']->name,
            'learner_name' => $previewData['learner']['name'],
            'learner_lrn' => $previewData['learner']['lrn'],
            'adviser_name' => $previewData['section']->adviser?->name ?? '',
            'general_average' => $previewData['general_average'],
            'promotion_remarks' => $previewData['promotion_remarks'],
        ];

        foreach ($scalarValues as $fieldKey => $value) {
            $this->writeScalar(
                $spreadsheet,
                (string) $fieldMaps->get($fieldKey)?->target_cell,
                $value,
                $fieldMaps->get($fieldKey)?->default_value,
            );
        }

        $rowFieldMap = [
            'subject_name_column' => 'subject_name',
            'subject_grade_column' => 'grade',
            'subject_remarks_column' => 'remarks',
        ];

        foreach ($rowFieldMap as $fieldKey => $rowProperty) {
            $this->writeRows(
                $spreadsheet,
                (string) $fieldMaps->get($fieldKey)?->target_cell,
                $previewData['subject_rows'],
                $rowProperty,
                $fieldMaps->get($fieldKey)?->default_value,
            );
        }

        $tempDirectory = base_path((string) config('sf9_exports.temp_directory', 'tmp/spreadsheets'));
        File::ensureDirectoryExists($tempDirectory);

        $tempFilePath = $tempDirectory.'/sf9-export-'.Str::uuid().'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(true);
        $writer->save($tempFilePath);

        return $tempFilePath;
    }

    private function writeScalar(
        Spreadsheet $spreadsheet,
        string $target,
        ?string $value,
        ?string $defaultValue,
    ): void {
        $resolvedTarget = $this->targetResolver->resolve($spreadsheet, $target);
        $resolvedValue = $this->resolvedValue($value, $defaultValue);

        $this->writeCell($resolvedTarget['worksheet'], $resolvedTarget['cell'], $resolvedValue, false);
    }

    private function writeRows(
        Spreadsheet $spreadsheet,
        string $target,
        array $rows,
        string $rowProperty,
        ?string $defaultValue,
    ): void {
        $resolvedTarget = $this->targetResolver->resolve($spreadsheet, $target);

        foreach ($rows as $index => $row) {
            $this->writeCell(
                $resolvedTarget['worksheet'],
                $resolvedTarget['column'].($resolvedTarget['row'] + $index),
                $this->resolvedValue($row[$rowProperty] ?? null, $defaultValue),
                $rowProperty === 'grade',
            );
        }
    }

    private function writeCell(
        mixed $worksheet,
        string $cell,
        mixed $value,
        bool $allowNumeric,
    ): void {
        if ($allowNumeric && is_numeric($value)) {
            $worksheet->setCellValue($cell, (float) $value);

            return;
        }

        $worksheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
    }

    private function resolvedValue(mixed $value, ?string $defaultValue): mixed
    {
        if ($value === null || $value === '') {
            return $defaultValue ?? '';
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }
}
