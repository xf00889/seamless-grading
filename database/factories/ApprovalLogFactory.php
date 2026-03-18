<?php

namespace Database\Factories;

use App\Enums\ApprovalAction;
use App\Models\ApprovalLog;
use App\Models\GradeSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalLog>
 */
class ApprovalLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'grade_submission_id' => GradeSubmission::factory(),
            'acted_by' => User::factory(),
            'action' => fake()->randomElement(ApprovalAction::cases()),
            'remarks' => fake()->sentence(),
            'metadata' => ['channel' => 'system'],
        ];
    }
}
