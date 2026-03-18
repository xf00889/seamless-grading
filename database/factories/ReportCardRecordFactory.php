<?php

namespace Database\Factories;

use App\Enums\GradingQuarter;
use App\Enums\TemplateDocumentType;
use App\Models\GradingPeriod;
use App\Models\ReportCardRecord;
use App\Models\SectionRoster;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCardRecord>
 */
class ReportCardRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_roster_id' => SectionRoster::factory(),
            'grading_period_id' => fn (array $attributes): int => $this->gradingPeriodIdForRoster(
                $attributes['section_roster_id'],
            ),
            'template_id' => Template::factory()->state([
                'document_type' => TemplateDocumentType::Sf9,
            ]),
            'generated_by' => User::factory(),
            'record_version' => 1,
            'template_version' => 1,
            'payload' => [
                'general_average' => fake()->randomFloat(2, 75, 98),
                'remarks' => fake()->randomElement(['Passed', 'Promoted']),
            ],
            'generated_at' => now(),
        ];
    }

    protected function gradingPeriodIdForRoster(int $sectionRosterId): int
    {
        $schoolYearId = SectionRoster::query()->findOrFail($sectionRosterId)->school_year_id;

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
