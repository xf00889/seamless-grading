<?php

namespace App\Actions\Admin\UserManagement\TeacherLoads;

use App\Models\TeacherLoad;
use Illuminate\Validation\ValidationException;

class DeleteTeacherLoadAction
{
    public function handle(TeacherLoad $teacherLoad): void
    {
        if ($teacherLoad->gradeSubmissions()->exists() || $teacherLoad->gradingSheetExports()->exists()) {
            throw ValidationException::withMessages([
                'record' => 'This teacher load already has linked submissions or exports and cannot be deleted.',
            ]);
        }

        $teacherLoad->delete();
    }
}
