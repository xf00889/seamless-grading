<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ReportCardRecord;
use App\Models\User;

final class ReportCardRecordPolicy
{
    public function viewAsRegistrar(User $user, ReportCardRecord $reportCardRecord): bool
    {
        return $user->can(PermissionName::ViewRegistrarRecords->value);
    }

    public function downloadAsAdviser(User $user, ReportCardRecord $reportCardRecord): bool
    {
        return $user->can(PermissionName::ViewAdvisorySections->value)
            && $reportCardRecord->section()->value('adviser_id') === $user->id;
    }
}
