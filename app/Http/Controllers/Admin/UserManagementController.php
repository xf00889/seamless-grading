<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherLoad;
use App\Models\User;
use App\Support\UserManagement\Navigation;
use Illuminate\Contracts\View\View;

class UserManagementController extends Controller
{
    public function __invoke(Navigation $navigation): View
    {
        $this->authorize('viewUserManagement', User::class);

        return view('admin.user-management.index', [
            'navigationItems' => $navigation->items(),
            'resourceCards' => [
                [
                    'label' => 'Users',
                    'route' => 'admin.user-management.users.index',
                    'count' => User::query()->count(),
                    'status' => User::query()->where('is_active', true)->count().' active',
                    'description' => 'Manage names, emails, roles, and account status without bypassing authorization rules.',
                ],
                [
                    'label' => 'Teacher Loads',
                    'route' => 'admin.user-management.teacher-loads.index',
                    'count' => TeacherLoad::query()->count(),
                    'status' => TeacherLoad::query()->where('is_active', true)->count().' active',
                    'description' => 'Assign teachers to sections and subjects while respecting school-year ownership and load uniqueness.',
                ],
            ],
        ]);
    }
}
