<?php

namespace Database\Factories;

use App\Enums\LearnerStatusAuditAction;
use App\Models\LearnerStatusAuditLog;
use App\Models\SectionRoster;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearnerStatusAuditLog>
 */
class LearnerStatusAuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_roster_id' => SectionRoster::factory(),
            'acted_by' => User::factory(),
            'action' => LearnerStatusAuditAction::YearEndStatusUpdated,
            'remarks' => fake()->sentence(),
            'metadata' => [
                'entity_type' => SectionRoster::class,
            ],
        ];
    }
}
