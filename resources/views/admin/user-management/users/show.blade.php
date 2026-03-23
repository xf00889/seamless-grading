<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">User management</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $managedUser->name }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ $managedUser->email }}</p>
            </div>

            <div class="flex flex-wrap gap-3">
                @can('update', $managedUser)
                    <a href="{{ route('admin.user-management.users.edit', $managedUser) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Edit user
                    </a>
                @endcan

                @if ($managedUser->is_active)
                    @can('deactivate', $managedUser)
                        <form method="POST" action="{{ route('admin.user-management.users.deactivate', $managedUser) }}" data-confirm-message="Deactivate this user account?">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-amber-300 px-4 py-3 text-sm font-semibold text-amber-700 transition hover:border-amber-400 hover:text-amber-800">
                                Deactivate
                            </button>
                        </form>
                    @endcan
                @else
                    @can('activate', $managedUser)
                        <form method="POST" action="{{ route('admin.user-management.users.activate', $managedUser) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-emerald-300 px-4 py-3 text-sm font-semibold text-emerald-700 transition hover:border-emerald-400 hover:text-emerald-800">
                                Activate
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.user-management.partials.navigation')

        <section class="grid gap-4 xl:grid-cols-4">
            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Role</p>
                <p class="mt-4 text-xl font-semibold text-slate-900">
                    {{ \App\Enums\RoleName::tryFrom($managedUser->roles->first()?->name ?? '')?->label() ?? 'No role' }}
                </p>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Status</p>
                <div class="mt-4">
                    <x-status-chip :tone="$managedUser->is_active ? 'emerald' : 'slate'">
                        {{ $managedUser->is_active ? 'Active' : 'Inactive' }}
                    </x-status-chip>
                </div>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Teacher loads</p>
                <p class="mt-4 text-xl font-semibold text-slate-900">{{ $managedUser->teacher_loads_count }}</p>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Advisory sections</p>
                <p class="mt-4 text-xl font-semibold text-slate-900">{{ $managedUser->advisory_sections_count }}</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
            <article class="content-card overflow-hidden">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Teacher load assignments</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Loads linked to this account are shown here for quick review.</p>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-4 py-3">Assignment</th>
                                <th class="px-4 py-3">Adviser</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                            @forelse ($managedUser->teacherLoads as $teacherLoad)
                                <tr>
                                    <td class="px-4 py-4">
                                        <a href="{{ route('admin.user-management.teacher-loads.show', $teacherLoad) }}" class="font-semibold text-slate-900 hover:text-slate-700">
                                            {{ $teacherLoad->subject->name }}
                                        </a>
                                        <p class="mt-1 text-slate-500">
                                            {{ $teacherLoad->schoolYear->name }} · {{ $teacherLoad->section->gradeLevel->name }} · {{ $teacherLoad->section->name }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-4 text-slate-500">
                                        {{ $teacherLoad->section->adviser?->name ?? 'No adviser assigned' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-status-chip :tone="$teacherLoad->is_active ? 'emerald' : 'slate'">
                                            {{ $teacherLoad->is_active ? 'Active' : 'Inactive' }}
                                        </x-status-chip>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">
                                        No teacher loads are linked to this user.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="content-card overflow-hidden">
                <h2 class="text-lg font-semibold text-slate-900">Advisory sections</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Section adviser responsibility stays on the section record and is not duplicated on teacher loads.</p>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-4 py-3">Section</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                            @forelse ($managedUser->advisorySections as $section)
                                <tr>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-slate-900">{{ $section->name }}</p>
                                        <p class="mt-1 text-slate-500">{{ $section->schoolYear->name }} · {{ $section->gradeLevel->name }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-status-chip :tone="$section->is_active ? 'emerald' : 'slate'">
                                            {{ $section->is_active ? 'Active' : 'Inactive' }}
                                        </x-status-chip>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-4 py-8 text-center text-sm text-slate-500">
                                        No advisory sections are linked to this user.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        @can('delete', $managedUser)
            <section class="content-card border border-rose-200">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Delete account</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Deletion is blocked while the user still owns teacher loads or advisory sections.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.user-management.users.destroy', $managedUser) }}" data-confirm-message="Delete this user account? This action cannot be undone.">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Delete user</x-danger-button>
                    </form>
                </div>
            </section>
        @endcan
    </div>
</x-app-layout>
