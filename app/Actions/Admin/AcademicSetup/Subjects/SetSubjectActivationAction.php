<?php

namespace App\Actions\Admin\AcademicSetup\Subjects;

use App\Models\Subject;

class SetSubjectActivationAction
{
    public function handle(Subject $subject, bool $isActive): Subject
    {
        $subject->forceFill(['is_active' => $isActive])->save();

        return $subject->refresh();
    }
}
