<?php

namespace Database\Seeders;

use App\Enums\GradingQuarter;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use Illuminate\Database\Seeder;

class AcademicStructureSeeder extends Seeder
{
    public function run(): void
    {
        $startYear = now()->month >= 6 ? now()->year : now()->subYear()->year;
        $endYear = $startYear + 1;

        SchoolYear::query()->update(['is_active' => false]);

        $schoolYear = SchoolYear::query()->updateOrCreate(
            ['name' => $startYear.'-'.$endYear],
            [
                'starts_on' => sprintf('%d-06-01', $startYear),
                'ends_on' => sprintf('%d-05-31', $endYear),
                'is_active' => true,
            ],
        );

        $quarters = [
            GradingQuarter::First->value => ['starts_on' => sprintf('%d-06-01', $startYear), 'ends_on' => sprintf('%d-08-31', $startYear)],
            GradingQuarter::Second->value => ['starts_on' => sprintf('%d-09-01', $startYear), 'ends_on' => sprintf('%d-10-31', $startYear)],
            GradingQuarter::Third->value => ['starts_on' => sprintf('%d-11-01', $startYear), 'ends_on' => sprintf('%d-01-31', $endYear)],
            GradingQuarter::Fourth->value => ['starts_on' => sprintf('%d-02-01', $endYear), 'ends_on' => sprintf('%d-04-30', $endYear)],
        ];

        foreach ($quarters as $quarter => $dates) {
            GradingPeriod::query()->updateOrCreate(
                [
                    'school_year_id' => $schoolYear->id,
                    'quarter' => $quarter,
                ],
                [
                    'starts_on' => $dates['starts_on'],
                    'ends_on' => $dates['ends_on'],
                    'is_open' => $quarter === GradingQuarter::First->value,
                ],
            );
        }
    }
}
