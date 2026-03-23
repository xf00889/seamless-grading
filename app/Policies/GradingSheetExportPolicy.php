<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\GradingSheetExport;
use App\Models\User;

final class GradingSheetExportPolicy
{
    public function download(User $user, GradingSheetExport $gradingSheetExport): bool
    {
        return $user->can(PermissionName::ViewTeacherGradingSheetExports->value)
            && $gradingSheetExport->teacherLoad()->value('teacher_id') === $user->id;
    }
}
