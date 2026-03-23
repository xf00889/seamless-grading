<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">User management</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Users</h1>
            </div>

            @can('create', \App\Models\User::class)
                <a href="{{ route('admin.user-management.users.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    New user
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.user-management.partials.navigation')

        <section class="content-card">
            <form method="GET" action="{{ route('admin.user-management.users.index') }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_220px_auto]">
                <div>
                    <x-input-label for="search" value="Search" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Search by name or email" />
                </div>

                <div>
                    <x-input-label for="role" value="Role" />
                    <select id="role" name="role" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All roles</option>
                        @foreach ($roleOptions as $roleOption)
                            <option value="{{ $roleOption->value }}" @selected($filters['role'] === $roleOption->value)>
                                {{ $roleOption->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All statuses</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('admin.user-management.users.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="content-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Linked records</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @forelse ($users as $managedUser)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $managedUser->name }}</p>
                                    <p class="mt-1 text-slate-500">{{ $managedUser->email }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    {{ \App\Enums\RoleName::tryFrom($managedUser->roles->first()?->name ?? '')?->label() ?? 'No role' }}
                                </td>
                                <td class="px-4 py-4">
                                    <x-status-chip :tone="$managedUser->is_active ? 'emerald' : 'slate'">
                                        {{ $managedUser->is_active ? 'Active' : 'Inactive' }}
                                    </x-status-chip>
                                </td>
                                <td class="px-4 py-4 text-slate-500">
                                    {{ $managedUser->teacher_loads_count }} loads, {{ $managedUser->advisory_sections_count }} advisory sections
                                </td>
                                <td class="px-4 py-4">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('admin.user-management.users.show', $managedUser)"
                                            icon="eye"
                                            title="View user"
                                            aria-label="View user"
                                        >
                                            View
                                        </x-table-action-button>
                                        @can('update', $managedUser)
                                            <x-table-action-button
                                                :href="route('admin.user-management.users.edit', $managedUser)"
                                                icon="edit"
                                                title="Edit user"
                                                aria-label="Edit user"
                                            >
                                                Edit
                                            </x-table-action-button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No users matched the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-4">
                {{ $users->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
