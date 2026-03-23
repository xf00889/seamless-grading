<?php

namespace App\Support\UserManagement;

final class Navigation
{
    public function items(): array
    {
        return [
            [
                'label' => 'Overview',
                'route' => 'admin.user-management',
                'active' => 'admin.user-management',
            ],
            [
                'label' => 'Users',
                'route' => 'admin.user-management.users.index',
                'active' => 'admin.user-management.users.*',
            ],
            [
                'label' => 'Teacher Loads',
                'route' => 'admin.user-management.teacher-loads.index',
                'active' => 'admin.user-management.teacher-loads.*',
            ],
        ];
    }
}
