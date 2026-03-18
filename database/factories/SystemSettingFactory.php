<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(3),
            'value' => ['enabled' => fake()->boolean()],
            'description' => fake()->sentence(),
            'is_public' => false,
        ];
    }
}
