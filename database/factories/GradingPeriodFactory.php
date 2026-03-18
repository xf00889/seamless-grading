<?php

namespace Database\Factories;

use App\Enums\GradingQuarter;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingPeriod>
 */
class GradingPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_year_id' => SchoolYear::factory(),
            'quarter' => fake()->randomElement(GradingQuarter::cases()),
            'starts_on' => null,
            'ends_on' => null,
            'is_open' => false,
        ];
    }
}
