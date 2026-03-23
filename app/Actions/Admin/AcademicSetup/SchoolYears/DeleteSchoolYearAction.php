<?php

namespace App\Actions\Admin\AcademicSetup\SchoolYears;

use App\Models\SchoolYear;
use Illuminate\Validation\ValidationException;

class DeleteSchoolYearAction
{
    public function handle(SchoolYear $schoolYear): void
    {
        if ($schoolYear->gradingPeriods()->exists() || $schoolYear->sections()->exists()) {
            throw ValidationException::withMessages([
                'record' => 'Delete the linked grading periods and sections before removing this school year.',
            ]);
        }

        $schoolYear->delete();
    }
}
