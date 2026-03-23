<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\ImportBatch;
use App\Models\Learner;
use App\Models\Section;
use App\Models\SectionRoster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectionRoster>
 */
class SectionRosterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'school_year_id' => fn (array $attributes): int => Section::query()
                ->findOrFail($attributes['section_id'])
                ->school_year_id,
            'learner_id' => Learner::factory(),
            'import_batch_id' => fn (array $attributes): int => ImportBatch::factory()->create([
                'section_id' => $attributes['section_id'],
            ])->id,
            'enrollment_status' => EnrollmentStatus::Active,
            'enrolled_on' => fake()->dateTimeBetween('-6 months', 'now'),
            'withdrawn_on' => null,
            'movement_reason' => null,
            'movement_recorded_at' => null,
            'movement_recorded_by' => null,
            'year_end_status' => null,
            'year_end_status_reason' => null,
            'year_end_status_set_at' => null,
            'year_end_status_set_by' => null,
            'is_official' => true,
        ];
    }
}
