<?php

namespace App\Actions\Admin\AcademicSetup\GradingPeriods;

use App\Models\GradingPeriod;
use Illuminate\Validation\ValidationException;

class DeleteGradingPeriodAction
{
    public function handle(GradingPeriod $gradingPeriod): void
    {
        if (
            $gradingPeriod->gradeSubmissions()->exists()
            || $gradingPeriod->gradingSheetExports()->exists()
            || $gradingPeriod->reportCardRecords()->exists()
        ) {
            throw ValidationException::withMessages([
                'record' => 'This grading period already has linked grading records and cannot be deleted.',
            ]);
        }

        $gradingPeriod->delete();
    }
}
