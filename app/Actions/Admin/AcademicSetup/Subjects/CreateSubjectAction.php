<?php

namespace App\Actions\Admin\AcademicSetup\Subjects;

use App\Models\Subject;

class CreateSubjectAction
{
    public function handle(array $attributes): Subject
    {
        return Subject::query()->create($attributes);
    }
}
