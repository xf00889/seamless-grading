<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use Illuminate\Database\Seeder;

class GradeLevelSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 12) as $level) {
            GradeLevel::query()->updateOrCreate(
                ['code' => 'GRADE-'.$level],
                [
                    'name' => 'Grade '.$level,
                    'sort_order' => $level,
                ],
            );
        }
    }
}
