<?php

namespace App\Actions\Admin\AcademicSetup\GradeLevels;

use App\Models\GradeLevel;
use Illuminate\Validation\ValidationException;

class DeleteGradeLevelAction
{
    public function handle(GradeLevel $gradeLevel): void
    {
        if ($gradeLevel->sections()->exists()) {
            throw ValidationException::withMessages([
                'record' => 'Delete or reassign linked sections before removing this grade level.',
            ]);
        }

        $gradeLevel->delete();
    }
}
