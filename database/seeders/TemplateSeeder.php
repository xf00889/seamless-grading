<?php

namespace Database\Seeders;

use App\Enums\TemplateDocumentType;
use App\Enums\TemplateMappingKind;
use App\Models\GradeLevel;
use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $grade7 = GradeLevel::query()->firstOrCreate(
            ['code' => 'GRADE-7'],
            ['name' => 'Grade 7', 'sort_order' => 7],
        );

        $templates = [
            [
                'code' => 'report-card',
                'name' => 'SF9 Report Card',
                'description' => 'Official SF9 report card export template for adviser-generated report cards.',
                'document_type' => TemplateDocumentType::Sf9,
                'grade_level_id' => $grade7->id,
                'version' => 1,
                'file_path' => 'templates/sf9/report-card-v1.xlsx',
                'file_disk' => 'local',
                'is_active' => true,
                'scope_key' => 'grade-level:'.$grade7->id,
                'active_scope_key' => 'sf9:grade-level:'.$grade7->id,
                'activated_at' => now(),
                'field_maps' => [
                    ['field_key' => 'school_name', 'target_cell' => 'B2'],
                    ['field_key' => 'school_year_name', 'target_cell' => 'F2'],
                    ['field_key' => 'grading_period_label', 'target_cell' => 'H2'],
                    ['field_key' => 'grade_level_name', 'target_cell' => 'B4'],
                    ['field_key' => 'section_name', 'target_cell' => 'F4'],
                    ['field_key' => 'learner_name', 'target_cell' => 'B6'],
                    ['field_key' => 'learner_lrn', 'target_cell' => 'F6'],
                    ['field_key' => 'adviser_name', 'target_cell' => 'B34'],
                    ['field_key' => 'subject_name_column', 'target_cell' => 'A12'],
                    ['field_key' => 'subject_grade_column', 'target_cell' => 'F12'],
                    ['field_key' => 'subject_remarks_column', 'target_cell' => 'H12'],
                    ['field_key' => 'general_average', 'target_cell' => 'H28'],
                    ['field_key' => 'promotion_remarks', 'target_cell' => 'H30'],
                ],
            ],
            [
                'code' => 'grading-sheet',
                'name' => 'Quarterly Grading Sheet',
                'description' => 'Spreadsheet template for quarterly grade entry and export by teaching load.',
                'document_type' => TemplateDocumentType::GradingSheet,
                'grade_level_id' => null,
                'version' => 1,
                'file_path' => 'templates/grading_sheet/grading-sheet-v1.xlsx',
                'file_disk' => 'local',
                'is_active' => true,
                'scope_key' => 'global',
                'active_scope_key' => 'grading_sheet:global',
                'activated_at' => now(),
                'field_maps' => [
                    ['field_key' => 'school_year_name', 'target_cell' => 'B2'],
                    ['field_key' => 'grading_period_label', 'target_cell' => 'F2'],
                    ['field_key' => 'grade_level_name', 'target_cell' => 'B4'],
                    ['field_key' => 'section_name', 'target_cell' => 'F4'],
                    ['field_key' => 'subject_name', 'target_cell' => 'B6'],
                    ['field_key' => 'teacher_name', 'target_cell' => 'F6'],
                    ['field_key' => 'adviser_name', 'target_cell' => 'B8'],
                    ['field_key' => 'learner_name_column', 'target_cell' => 'A12'],
                    ['field_key' => 'learner_lrn_column', 'target_cell' => 'B12'],
                    ['field_key' => 'learner_sex_column', 'target_cell' => 'C12'],
                    ['field_key' => 'learner_grade_column', 'target_cell' => 'D12'],
                    ['field_key' => 'learner_remarks_column', 'target_cell' => 'E12'],
                ],
            ],
            [
                'code' => 'report-card',
                'name' => 'SF10 Permanent Record',
                'description' => 'Spreadsheet template for adviser-prepared SF10 draft exports.',
                'document_type' => TemplateDocumentType::Sf10,
                'grade_level_id' => null,
                'version' => 1,
                'file_path' => 'templates/sf10/report-card-v1.xlsx',
                'file_disk' => 'local',
                'is_active' => false,
                'scope_key' => 'global',
                'active_scope_key' => null,
                'activated_at' => null,
                'field_maps' => [
                    ['field_key' => 'school_name', 'target_cell' => 'B2'],
                    ['field_key' => 'learner_name', 'target_cell' => 'B4'],
                    ['field_key' => 'learner_lrn', 'target_cell' => 'F4'],
                    ['field_key' => 'grade_level_name', 'target_cell' => 'B6'],
                    ['field_key' => 'school_year_name', 'target_cell' => 'F6'],
                    ['field_key' => 'section_name', 'target_cell' => 'B8'],
                    ['field_key' => 'adviser_name', 'target_cell' => 'F8'],
                    ['field_key' => 'learner_status', 'target_cell' => 'B10'],
                    ['field_key' => 'general_average', 'target_cell' => 'F10'],
                    ['field_key' => 'subject_name_column', 'target_cell' => 'A14'],
                    ['field_key' => 'final_rating_column', 'target_cell' => 'F14'],
                    ['field_key' => 'action_taken_column', 'target_cell' => 'H14'],
                ],
            ],
        ];

        foreach ($templates as $attributes) {
            $definitions = collect(config('templates.definitions.'.$attributes['document_type']->value))
                ->keyBy('field_key');

            $template = Template::query()->updateOrCreate(
                [
                    'document_type' => $attributes['document_type'],
                    'code' => $attributes['code'],
                    'version' => $attributes['version'],
                ],
                [
                    'name' => $attributes['name'],
                    'description' => $attributes['description'],
                    'grade_level_id' => $attributes['grade_level_id'],
                    'scope_key' => $attributes['scope_key'],
                    'file_path' => $attributes['file_path'],
                    'file_disk' => $attributes['file_disk'],
                    'is_active' => $attributes['is_active'],
                    'active_scope_key' => $attributes['active_scope_key'],
                    'activated_at' => $attributes['activated_at'],
                ],
            );

            foreach ($attributes['field_maps'] as $fieldMap) {
                $template->fieldMaps()->updateOrCreate(
                    ['field_key' => $fieldMap['field_key']],
                    [
                        'mapping_kind' => $definitions->get($fieldMap['field_key'])['default_mapping_kind']
                            ?? TemplateMappingKind::FixedCell->value,
                        'target_cell' => $fieldMap['target_cell'],
                        'sheet_name' => null,
                        'mapping_config' => null,
                        'default_value' => null,
                        'is_required' => (bool) ($definitions->get($fieldMap['field_key'])['required'] ?? true),
                    ],
                );
            }
        }
    }
}
