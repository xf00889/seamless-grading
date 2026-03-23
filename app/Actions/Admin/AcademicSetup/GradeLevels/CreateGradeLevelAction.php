<?php

namespace App\Actions\Admin\AcademicSetup\GradeLevels;

use App\Models\GradeLevel;

class CreateGradeLevelAction
{
    public function handle(array $attributes): GradeLevel
    {
        return GradeLevel::query()->create($attributes);
    }
}
