<?php

namespace Database\Seeders;

use App\Enums\TemplateDocumentType;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use App\Models\SystemSetting;
use App\Models\Template;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $schoolYear = SchoolYear::query()->where('is_active', true)->first();
        $gradingPeriod = $schoolYear
            ? GradingPeriod::query()
                ->where('school_year_id', $schoolYear->id)
                ->where('is_open', true)
                ->first()
            : null;

        $sf9Template = Template::query()
            ->where('document_type', TemplateDocumentType::Sf9)
            ->where('is_active', true)
            ->first();

        $gradingSheetTemplate = Template::query()
            ->where('document_type', TemplateDocumentType::GradingSheet)
            ->where('is_active', true)
            ->first();

        $settings = [
            'school.profile' => [
                'value' => ['name' => 'Seamless Grading Demo School'],
                'description' => 'Basic school profile defaults.',
                'is_public' => true,
            ],
            'academic.active_school_year' => [
                'value' => ['school_year_id' => $schoolYear?->id],
                'description' => 'Active school year reference.',
                'is_public' => false,
            ],
            'academic.open_grading_period' => [
                'value' => ['grading_period_id' => $gradingPeriod?->id],
                'description' => 'Open grading period reference.',
                'is_public' => false,
            ],
            'templates.active.sf9' => [
                'value' => ['template_id' => $sf9Template?->id, 'version' => $sf9Template?->version],
                'description' => 'Active SF9 template reference.',
                'is_public' => false,
            ],
            'templates.active.grading_sheet' => [
                'value' => ['template_id' => $gradingSheetTemplate?->id, 'version' => $gradingSheetTemplate?->version],
                'description' => 'Active grading sheet template reference.',
                'is_public' => false,
            ],
        ];

        foreach ($settings as $key => $setting) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                $setting,
            );
        }
    }
}
