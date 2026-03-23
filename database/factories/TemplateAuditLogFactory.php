<?php

namespace Database\Factories;

use App\Enums\TemplateAuditAction;
use App\Models\Template;
use App\Models\TemplateAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateAuditLog>
 */
class TemplateAuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'acted_by' => User::factory(),
            'action' => fake()->randomElement(TemplateAuditAction::cases()),
            'remarks' => fake()->sentence(),
            'metadata' => ['channel' => 'system'],
        ];
    }
}
