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
            'section_id' => fn (array $attributes): int => SectionRoster::query()
                ->findOrFail($attributes['section_roster_id'])
                ->section_id,
            'school_year_id' => fn (array $attributes): int => SectionRoster::query()
                ->findOrFail($attributes['section_roster_id'])
                ->school_year_id,
            'learner_id' => fn (array $attributes): int => SectionRoster::query()
                ->findOrFail($attributes['section_roster_id'])
                ->learner_id,
            'grading_period_id' => fn (array $attributes): int => $this->gradingPeriodIdForRoster(
                $attributes['section_roster_id'],
            ),
            'template_id' => Template::factory()->state([
                'document_type' => TemplateDocumentType::Sf9,
            ]),
            'document_type' => fn (array $attributes): TemplateDocumentType => Template::query()
                ->findOrFail($attributes['template_id'])
                ->document_type,
            'generated_by' => User::factory(),
            'record_version' => 1,
            'template_version' => 1,
            'file_name' => fn (array $attributes): string => $this->documentTypeValue($attributes).'-'.fake()->uuid().'.xlsx',
            'file_disk' => 'local',
            'file_path' => fn (array $attributes): string => 'exports/'.$this->documentTypeValue($attributes).'/'.fake()->uuid().'.xlsx',
            'is_finalized' => false,
            'finalized_at' => null,
            'finalized_by' => null,
            'payload' => [
                'source_hash' => fake()->sha256(),
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

    private function documentTypeValue(array $attributes): string
    {
        $documentType = $attributes['document_type'] ?? TemplateDocumentType::Sf9;

        return $documentType instanceof TemplateDocumentType
            ? $documentType->value
            : (string) $documentType;
    }
}
