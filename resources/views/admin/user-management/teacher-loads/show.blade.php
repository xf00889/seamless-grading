<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">User management</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $teacherLoad->subject->name }}</h1>
                <p class="mt-2 text-sm text-slate-600">
                    {{ $teacherLoad->teacher->name }} · {{ $teacherLoad->schoolYear->name }} · {{ $teacherLoad->section->name }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                @can('update', $teacherLoad)
                    <a href="{{ route('admin.user-management.teacher-loads.edit', $teacherLoad) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Edit load
                    </a>
                @endcan

                @if ($teacherLoad->is_active)
                    @can('deactivate', $teacherLoad)
                        <form method="POST" action="{{ route('admin.user-management.teacher-loads.deactivate', $teacherLoad) }}" data-confirm-message="Deactivate this teacher load?">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-amber-300 px-4 py-3 text-sm font-semibold text-amber-700 transition hover:border-amber-400 hover:text-amber-800">
                                Deactivate
                            </button>
                        </form>
                    @endcan
                @else
                    @can('activate', $teacherLoad)
                        <form method="POST" action="{{ route('admin.user-management.teacher-loads.activate', $teacherLoad) }}">
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
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Teacher</p>
                <p class="mt-4 text-xl font-semibold text-slate-900">{{ $teacherLoad->teacher->name }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $teacherLoad->teacher->email }}</p>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Section</p>
                <p class="mt-4 text-xl font-semibold text-slate-900">{{ $teacherLoad->section->name }}</p>
                <p class="mt-2 text-sm text-slate-500">{{ $teacherLoad->section->gradeLevel->name }} · {{ $teacherLoad->schoolYear->name }}</p>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Adviser</p>
                <p class="mt-4 text-xl font-semibold text-slate-900">{{ $teacherLoad->section->adviser?->name ?? 'No adviser assigned' }}</p>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Status</p>
                <div class="mt-4">
                    <x-status-chip :tone="$teacherLoad->is_active ? 'emerald' : 'slate'">
                        {{ $teacherLoad->is_active ? 'Active' : 'Inactive' }}
                    </x-status-chip>
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <article class="content-card">
                <h2 class="text-lg font-semibold text-slate-900">Assignment details</h2>
                <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Subject</dt>
                        <dd class="mt-2 text-sm text-slate-700">{{ $teacherLoad->subject->code }} · {{ $teacherLoad->subject->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Teacher role</dt>
                        <dd class="mt-2 text-sm text-slate-700">
                            {{ \App\Enums\RoleName::tryFrom($teacherLoad->teacher->roles->first()?->name ?? '')?->label() ?? 'No role' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Linked submissions</dt>
                        <dd class="mt-2 text-sm text-slate-700">{{ $teacherLoad->grade_submissions_count }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Linked exports</dt>
                        <dd class="mt-2 text-sm text-slate-700">{{ $teacherLoad->grading_sheet_exports_count }}</dd>
                    </div>
                </dl>
            </article>

            <article class="content-card overflow-hidden">
                <h2 class="text-lg font-semibold text-slate-900">Submission history</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Existing submission records make this load unsafe to reassign or delete.</p>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-4 py-3">Period</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                            @forelse ($teacherLoad->gradeSubmissions as $gradeSubmission)
                                <tr>
                                    <td class="px-4 py-4">
                                        {{ $gradeSubmission->gradingPeriod->quarter->label() }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-status-chip tone="slate">
                                            {{ ucfirst($gradeSubmission->status->value) }}
                                        </x-status-chip>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-4 py-8 text-center text-sm text-slate-500">
                                        No submissions are linked to this teacher load yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        @can('delete', $teacherLoad)
            <section class="content-card border border-rose-200">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Delete teacher load</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Deletion is blocked once submissions or exports exist for this load.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.user-management.teacher-loads.destroy', $teacherLoad) }}" data-confirm-message="Delete this teacher load? This action cannot be undone.">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>Delete load</x-danger-button>
                    </form>
                </div>
            </section>
        @endcan
    </div>
</x-app-layout>
