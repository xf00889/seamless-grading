<?php

namespace App\Actions\Admin\AcademicSetup\Subjects;

use App\Models\Subject;
use Illuminate\Validation\ValidationException;

class DeleteSubjectAction
{
    public function handle(Subject $subject): void
    {
        if ($subject->teacherLoads()->exists()) {
            throw ValidationException::withMessages([
                'record' => 'Remove linked teacher loads before deleting this subject.',
            ]);
        }

        $subject->delete();
    }
}
