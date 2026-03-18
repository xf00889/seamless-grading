<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['code' => 'FIL', 'name' => 'Filipino', 'short_name' => 'Fil'],
            ['code' => 'ENG', 'name' => 'English', 'short_name' => 'Eng'],
            ['code' => 'MAT', 'name' => 'Mathematics', 'short_name' => 'Math'],
            ['code' => 'SCI', 'name' => 'Science', 'short_name' => 'Sci'],
            ['code' => 'AP', 'name' => 'Araling Panlipunan', 'short_name' => 'AP'],
            ['code' => 'ESP', 'name' => 'Edukasyon sa Pagpapakatao', 'short_name' => 'ESP'],
            ['code' => 'MAPEH', 'name' => 'Music, Arts, PE, and Health', 'short_name' => 'MAPEH'],
            ['code' => 'TLE', 'name' => 'Technology and Livelihood Education', 'short_name' => 'TLE'],
        ];

        foreach ($subjects as $subject) {
            Subject::query()->updateOrCreate(
                ['code' => $subject['code']],
                [
                    'name' => $subject['name'],
                    'short_name' => $subject['short_name'],
                    'is_core' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
