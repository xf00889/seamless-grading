<?php

namespace Database\Factories;

use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    public function definition(): array
    {
        $documentType = fake()->randomElement(TemplateDocumentType::cases());
        $code = fake()->unique()->slug(2);
        $extension = 'xlsx';
        $gradeLevelId = fake()->boolean(40) ? GradeLevel::factory() : null;

        return [
            'code' => $code,
            'name' => ucwords(str_replace('-', ' ', $code)),
            'description' => fake()->sentence(),
            'document_type' => $documentType,
            'grade_level_id' => $gradeLevelId,
            'scope_key' => fn (array $attributes): string => $attributes['grade_level_id'] === null
                ? 'global'
                : 'grade-level:'.$attributes['grade_level_id'],
            'version' => 1,
            'file_path' => sprintf('templates/%s/%s-v1.%s', $documentType->value, $code, $extension),
            'file_disk' => 'local',
            'active_scope_key' => null,
            'is_active' => false,
            'activated_at' => null,
            'deactivated_at' => null,
        ];
    }
}
