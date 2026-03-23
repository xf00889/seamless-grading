<?php

namespace Database\Factories;

use App\Enums\TemplateMappingKind;
use App\Models\Template;
use App\Models\TemplateFieldMap;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TemplateFieldMap>
 */
class TemplateFieldMapFactory extends Factory
{
    public function definition(): array
    {
        $fieldKey = Str::snake(fake()->unique()->words(2, true));

        return [
            'template_id' => Template::factory(),
            'field_key' => $fieldKey,
            'mapping_kind' => TemplateMappingKind::FixedCell,
            'target_cell' => fake()->randomElement(['A1', 'B4', 'C12', 'DATA_START']),
            'sheet_name' => null,
            'mapping_config' => null,
            'default_value' => null,
            'is_required' => true,
        ];
    }
}
