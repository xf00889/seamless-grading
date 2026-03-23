<?php

namespace App\Http\Controllers\Admin\UserManagement;

use App\Actions\Admin\UserManagement\Users\CreateUserAction;
use App\Actions\Admin\UserManagement\Users\DeleteUserAction;
use App\Actions\Admin\UserManagement\Users\SetUserActivationAction;
use App\Actions\Admin\UserManagement\Users\UpdateUserAction;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\UserRequest;
use App\Models\User;
use App\Support\UserManagement\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly Navigation $navigation)
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $role = (string) $request->string('role');
        $status = (string) $request->string('status');

        $users = User::query()
            ->with('roles')
            ->withCount(['teacherLoads', 'advisorySections'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($role !== '', fn ($query) => $query->role($role))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.user-management.users.index', [
            'navigationItems' => $this->navigation->items(),
            'users' => $users,
            'filters' => compact('search', 'role', 'status'),
            'roleOptions' => RoleName::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.user-management.users.create', [
            'navigationItems' => $this->navigation->items(),
            'managedUser' => new User,
            'roleOptions' => RoleName::cases(),
        ]);
    }

    public function store(UserRequest $request, CreateUserAction $action): RedirectResponse
    {
        $user = $action->handle($request->validated());

        return redirect()
            ->route('admin.user-management.users.show', $user)
            ->with('status', 'User account created successfully.');
    }

    public function show(User $user): View
    {
        $user->loadCount(['teacherLoads', 'advisorySections']);
        $user->load([
            'roles',
            'advisorySections' => fn ($query) => $query
                ->with(['schoolYear', 'gradeLevel'])
                ->orderByDesc('school_year_id')
                ->orderBy('name'),
            'teacherLoads' => fn ($query) => $query
                ->with(['schoolYear', 'section.gradeLevel', 'section.adviser', 'subject'])
                ->orderByDesc('school_year_id')
                ->orderByDesc('is_active'),
        ]);

        return view('admin.user-management.users.show', [
            'navigationItems' => $this->navigation->items(),
            'managedUser' => $user,
        ]);
    }

    public function edit(User $user): View
    {
        $user->load('roles');

        return view('admin.user-management.users.edit', [
            'navigationItems' => $this->navigation->items(),
            'managedUser' => $user,
            'roleOptions' => RoleName::cases(),
        ]);
    }

    public function update(
        UserRequest $request,
        User $user,
        UpdateUserAction $action,
    ): RedirectResponse {
        $action->handle($request->user(), $user, $request->validated());

        return redirect()
            ->route('admin.user-management.users.show', $user)
            ->with('status', 'User account updated successfully.');
    }

    public function destroy(
        Request $request,
        User $user,
        DeleteUserAction $action,
    ): RedirectResponse {
        $action->handle($request->user(), $user);

        return redirect()
            ->route('admin.user-management.users.index')
            ->with('status', 'User account deleted successfully.');
    }

    public function activate(
        Request $request,
        User $user,
        SetUserActivationAction $action,
    ): RedirectResponse {
        $this->authorize('activate', $user);
        $action->handle($request->user(), $user, true);

        return redirect()
            ->route('admin.user-management.users.show', $user)
            ->with('status', 'User account activated successfully.');
    }

    public function deactivate(
        Request $request,
        User $user,
        SetUserActivationAction $action,
    ): RedirectResponse {
        $this->authorize('deactivate', $user);
        $action->handle($request->user(), $user, false);

        return redirect()
            ->route('admin.user-management.users.show', $user)
            ->with('status', 'User account deactivated successfully.');
    }
}
