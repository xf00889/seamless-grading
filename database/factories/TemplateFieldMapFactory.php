<?php

namespace Database\Factories;

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
            'source_column' => Str::snake(fake()->words(2, true)),
            'default_value' => null,
            'is_required' => true,
        ];
    }
}
