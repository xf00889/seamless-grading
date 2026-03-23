<?php

namespace App\Actions\Admin\AcademicSetup\GradeLevels;

use App\Models\GradeLevel;

class UpdateGradeLevelAction
{
    public function handle(GradeLevel $gradeLevel, array $attributes): GradeLevel
    {
        $gradeLevel->fill($attributes)->save();

        return $gradeLevel->refresh();
    }
}
