<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolYear>
 */
class SchoolYearFactory extends Factory
{
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2024, 2035);
        $endYear = $startYear + 1;

        return [
            'name' => $startYear.'-'.$endYear,
            'starts_on' => sprintf('%d-06-01', $startYear),
            'ends_on' => sprintf('%d-05-31', $endYear),
            'is_active' => false,
        ];
    }
}
