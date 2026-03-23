<?php

namespace App\Actions\Admin\AcademicSetup\SchoolYears;

use App\Models\SchoolYear;

class CreateSchoolYearAction
{
    public function handle(array $attributes): SchoolYear
    {
        return SchoolYear::query()->create($attributes);
    }
}
