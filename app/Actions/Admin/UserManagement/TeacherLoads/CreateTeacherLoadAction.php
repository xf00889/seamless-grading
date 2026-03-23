<?php

namespace App\Actions\Admin\UserManagement\TeacherLoads;

use App\Models\TeacherLoad;

class CreateTeacherLoadAction
{
    public function handle(array $attributes): TeacherLoad
    {
        return TeacherLoad::query()->create([
            ...$attributes,
            'is_active' => true,
        ]);
    }
}
