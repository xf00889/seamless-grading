<?php

namespace App\Actions\Admin\AcademicSetup\SchoolYears;

use App\Models\SchoolYear;
use Illuminate\Support\Facades\DB;

class SetSchoolYearActivationAction
{
    public function handle(SchoolYear $schoolYear, bool $isActive): SchoolYear
    {
        DB::transaction(function () use ($schoolYear, $isActive): void {
            if ($isActive) {
                SchoolYear::query()->whereKeyNot($schoolYear->getKey())->update(['is_active' => false]);
            }

            $schoolYear->forceFill(['is_active' => $isActive])->save();
        });

        return $schoolYear->refresh();
    }
}
