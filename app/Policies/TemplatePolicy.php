<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ViewTemplateManagement->value);
    }

    public function view(User $user, Template $template): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ManageTemplates->value);
    }

    public function activate(User $user, Template $template): bool
    {
        return $this->create($user);
    }

    public function deactivate(User $user, Template $template): bool
    {
        return $this->create($user);
    }

    public function updateMappings(User $user, Template $template): bool
    {
        return $this->create($user);
    }

    public function history(User $user, Template $template): bool
    {
        return $this->viewAny($user);
    }
}
