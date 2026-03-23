<?php

namespace App\Actions\Admin\UserManagement\TeacherLoads;

use App\Models\TeacherLoad;

class SetTeacherLoadActivationAction
{
    public function handle(TeacherLoad $teacherLoad, bool $isActive): void
    {
        $teacherLoad->forceFill([
            'is_active' => $isActive,
        ])->save();
    }
}
