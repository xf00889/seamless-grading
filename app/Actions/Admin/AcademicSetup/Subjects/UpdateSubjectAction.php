<?php

namespace App\Actions\Admin\AcademicSetup\Subjects;

use App\Models\Subject;

class UpdateSubjectAction
{
    public function handle(Subject $subject, array $attributes): Subject
    {
        $subject->fill($attributes)->save();

        return $subject->refresh();
    }
}
