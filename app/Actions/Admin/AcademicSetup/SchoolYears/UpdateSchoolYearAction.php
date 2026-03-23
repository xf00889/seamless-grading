<?php

namespace App\Actions\Admin\AcademicSetup\SchoolYears;

use App\Models\SchoolYear;

class UpdateSchoolYearAction
{
    public function handle(SchoolYear $schoolYear, array $attributes): SchoolYear
    {
        $schoolYear->fill($attributes)->save();

        return $schoolYear->refresh();
    }
}
