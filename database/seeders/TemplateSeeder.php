<?php

namespace Database\Seeders;

use App\Enums\TemplateDocumentType;
use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'report-card',
                'name' => 'SF9 Report Card',
                'description' => 'Official SF9 report card export template for adviser-generated report cards.',
                'document_type' => TemplateDocumentType::Sf9,
                'version' => 1,
                'file_path' => 'templates/sf9/report-card-v1.docx',
                'is_active' => true,
                'field_maps' => [
                    ['field_key' => 'learner_name', 'source_column' => 'learner.full_name'],
                    ['field_key' => 'school_year', 'source_column' => 'school_year.name'],
                ],
            ],
            [
                'code' => 'grading-sheet',
                'name' => 'Quarterly Grading Sheet',
                'description' => 'Spreadsheet template for quarterly grade entry and export by teaching load.',
                'document_type' => TemplateDocumentType::GradingSheet,
                'version' => 1,
                'file_path' => 'templates/grading_sheet/grading-sheet-v1.xlsx',
                'is_active' => true,
                'field_maps' => [
                    ['field_key' => 'subject_name', 'source_column' => 'teacher_load.subject.name'],
                    ['field_key' => 'teacher_name', 'source_column' => 'teacher_load.teacher.name'],
                ],
            ],
            [
                'code' => 'report-card',
                'name' => 'SF10 Permanent Record',
                'description' => 'Reserved versioned template slot for future SF10 support.',
                'document_type' => TemplateDocumentType::Sf10,
                'version' => 1,
                'file_path' => 'templates/sf10/report-card-v1.docx',
                'is_active' => false,
                'field_maps' => [],
            ],
        ];

        foreach ($templates as $attributes) {
            $template = Template::query()->updateOrCreate(
                [
                    'document_type' => $attributes['document_type'],
                    'code' => $attributes['code'],
                    'version' => $attributes['version'],
                ],
                [
                    'name' => $attributes['name'],
                    'description' => $attributes['description'],
                    'file_path' => $attributes['file_path'],
                    'is_active' => $attributes['is_active'],
                ],
            );

            foreach ($attributes['field_maps'] as $fieldMap) {
                $template->fieldMaps()->updateOrCreate(
                    ['field_key' => $fieldMap['field_key']],
                    [
                        'source_column' => $fieldMap['source_column'],
                        'default_value' => null,
                        'is_required' => true,
                    ],
                );
            }
        }
    }
}
