<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">User management</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Teacher Loads</h1>
            </div>

            @can('create', \App\Models\TeacherLoad::class)
                <a href="{{ route('admin.user-management.teacher-loads.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Assign teacher load
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.user-management.partials.navigation')

        <section class="content-card">
            <form method="GET" action="{{ route('admin.user-management.teacher-loads.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_220px_220px_220px_180px_auto]">
                <div>
                    <x-input-label for="search" value="Search" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Teacher, section, subject, or school year" />
                </div>

                <div>
                    <x-input-label for="school_year_id" value="School year" />
                    <select id="school_year_id" name="school_year_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All school years</option>
                        @foreach ($schoolYears as $schoolYear)
                            <option value="{{ $schoolYear->id }}" @selected($filters['school_year_id'] === $schoolYear->id)>
                                {{ $schoolYear->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="teacher_id" value="Teacher" />
                    <select id="teacher_id" name="teacher_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All teachers</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === $teacher->id)>
                                {{ $teacher->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="section_id" value="Section" />
                    <select id="section_id" name="section_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected($filters['section_id'] === $section->id)>
                                {{ $section->name }} · {{ $section->schoolYear->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="subject_id" value="Subject" />
                    <select id="subject_id" name="subject_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All subjects</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected($filters['subject_id'] === $subject->id)>
                                {{ $subject->code }}
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
                    <a href="{{ route('admin.user-management.teacher-loads.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
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
                            <th class="px-4 py-3">Teacher</th>
                            <th class="px-4 py-3">Assignment</th>
                            <th class="px-4 py-3">Adviser</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Activity</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @forelse ($teacherLoads as $teacherLoad)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $teacherLoad->teacher->name }}</p>
                                    <p class="mt-1 text-slate-500">{{ $teacherLoad->teacher->email }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $teacherLoad->subject->name }}</p>
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
                                <td class="px-4 py-4 text-slate-500">
                                    {{ $teacherLoad->grade_submissions_count }} submissions
                                </td>
                                <td class="px-4 py-4">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('admin.user-management.teacher-loads.show', $teacherLoad)"
                                            icon="eye"
                                            title="View teacher load"
                                            aria-label="View teacher load"
                                        >
                                            View
                                        </x-table-action-button>
                                        @can('update', $teacherLoad)
                                            <x-table-action-button
                                                :href="route('admin.user-management.teacher-loads.edit', $teacherLoad)"
                                                icon="edit"
                                                title="Edit teacher load"
                                                aria-label="Edit teacher load"
                                            >
                                                Edit
                                            </x-table-action-button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No teacher loads matched the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-4">
                {{ $teacherLoads->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
