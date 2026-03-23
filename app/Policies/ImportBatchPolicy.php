<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\ImportBatch;
use App\Models\User;

final class ImportBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ViewSf1Imports->value);
    }

    public function view(User $user, ImportBatch $importBatch): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageSf1Imports->value);
    }

    public function update(User $user, ImportBatch $importBatch): bool
    {
        return $this->create($user);
    }

    public function resolve(User $user, ImportBatch $importBatch): bool
    {
        return $this->create($user);
    }

    public function confirm(User $user, ImportBatch $importBatch): bool
    {
        return $this->create($user);
    }
}
