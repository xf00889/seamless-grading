<?php

namespace Database\Factories;

use App\Enums\GradingQuarter;
use App\Enums\TemplateDocumentType;
use App\Models\GradingPeriod;
use App\Models\GradingSheetExport;
use App\Models\TeacherLoad;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingSheetExport>
 */
class GradingSheetExportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'teacher_load_id' => TeacherLoad::factory(),
            'grading_period_id' => fn (array $attributes): int => $this->gradingPeriodIdForTeacherLoad(
                $attributes['teacher_load_id'],
            ),
            'template_id' => Template::factory()->state([
                'document_type' => TemplateDocumentType::GradingSheet,
            ]),
            'exported_by' => User::factory(),
            'version' => 1,
            'template_version' => 1,
            'file_name' => fake()->lexify('grading-sheet-????').'.xlsx',
            'file_disk' => 'local',
            'file_path' => 'exports/'.fake()->uuid().'.xlsx',
            'exported_at' => now(),
        ];
    }

    protected function gradingPeriodIdForTeacherLoad(int $teacherLoadId): int
    {
        $schoolYearId = TeacherLoad::query()->findOrFail($teacherLoadId)->school_year_id;

        return GradingPeriod::query()->firstOrCreate(
            [
                'school_year_id' => $schoolYearId,
                'quarter' => GradingQuarter::First,
            ],
            [
                'starts_on' => null,
                'ends_on' => null,
                'is_open' => false,
            ],
        )->id;
    }
}
