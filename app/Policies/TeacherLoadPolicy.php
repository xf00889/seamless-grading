<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\TeacherLoad;
use App\Models\User;

final class TeacherLoadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ViewUserManagement->value);
    }

    public function view(User $user, TeacherLoad $teacherLoad): bool
    {
        return $this->viewAny($user);
    }

    public function viewLearners(User $user, TeacherLoad $teacherLoad): bool
    {
        return $user->can(PermissionName::ViewTeacherLoads->value)
            && $teacherLoad->teacher_id === $user->id;
    }

    public function enterGrades(User $user, TeacherLoad $teacherLoad): bool
    {
        return $user->can(PermissionName::ViewTeacherGradeEntry->value)
            && $teacherLoad->teacher_id === $user->id;
    }

    public function previewGradingSheet(User $user, TeacherLoad $teacherLoad): bool
    {
        return $user->can(PermissionName::ViewTeacherGradingSheetExports->value)
            && $teacherLoad->teacher_id === $user->id;
    }

    public function exportGradingSheet(User $user, TeacherLoad $teacherLoad): bool
    {
        return $user->can(PermissionName::ExportTeacherGradingSheets->value)
            && $teacherLoad->teacher_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageTeacherLoads->value);
    }

    public function update(User $user, TeacherLoad $teacherLoad): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, TeacherLoad $teacherLoad): bool
    {
        return $this->create($user);
    }

    public function activate(User $user, TeacherLoad $teacherLoad): bool
    {
        return $this->create($user);
    }

    public function deactivate(User $user, TeacherLoad $teacherLoad): bool
    {
        return $this->create($user);
    }
}
