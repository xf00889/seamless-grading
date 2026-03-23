<?php

namespace App\Actions\Admin\UserManagement\TeacherLoads;

use App\Models\TeacherLoad;
use Illuminate\Validation\ValidationException;

class UpdateTeacherLoadAction
{
    public function handle(TeacherLoad $teacherLoad, array $attributes): void
    {
        if (
            $teacherLoad->gradeSubmissions()->exists()
            && $this->assignmentHasChanged($teacherLoad, $attributes)
        ) {
            throw ValidationException::withMessages([
                'record' => 'This teacher load already has linked submissions and cannot be reassigned. Deactivate it and create a new load instead.',
            ]);
        }

        if (
            $teacherLoad->gradingSheetExports()->exists()
            && $this->assignmentHasChanged($teacherLoad, $attributes)
        ) {
            throw ValidationException::withMessages([
                'record' => 'This teacher load already has linked exports and cannot be reassigned. Deactivate it and create a new load instead.',
            ]);
        }

        $teacherLoad->update($attributes);
    }

    private function assignmentHasChanged(TeacherLoad $teacherLoad, array $attributes): bool
    {
        return (int) $teacherLoad->teacher_id !== (int) $attributes['teacher_id']
            || (int) $teacherLoad->school_year_id !== (int) $attributes['school_year_id']
            || (int) $teacherLoad->section_id !== (int) $attributes['section_id']
            || (int) $teacherLoad->subject_id !== (int) $attributes['subject_id'];
    }
}
