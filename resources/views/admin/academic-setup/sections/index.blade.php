<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Academic setup</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Sections</h1>
            </div>

            @can('create', \App\Models\Section::class)
                <a href="{{ route('admin.academic-setup.sections.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    New section
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.academic-setup.partials.navigation')

        <section class="content-card">
            <form method="GET" action="{{ route('admin.academic-setup.sections.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_220px_180px_auto]">
                <div>
                    <x-input-label for="search" value="Search" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Search by section, school year, grade level, or adviser" />
                </div>

                <div>
                    <x-input-label for="school_year_id" value="School year" />
                    <select id="school_year_id" name="school_year_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All school years</option>
                        @foreach ($schoolYears as $schoolYear)
                            <option value="{{ $schoolYear->id }}" @selected($filters['school_year_id'] === $schoolYear->id)>{{ $schoolYear->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="grade_level_id" value="Grade level" />
                    <select id="grade_level_id" name="grade_level_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All grade levels</option>
                        @foreach ($gradeLevels as $gradeLevel)
                            <option value="{{ $gradeLevel->id }}" @selected($filters['grade_level_id'] === $gradeLevel->id)>{{ $gradeLevel->name }}</option>
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
                    <a href="{{ route('admin.academic-setup.sections.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
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
                            <th class="px-4 py-3">Section</th>
                            <th class="px-4 py-3">School year</th>
                            <th class="px-4 py-3">Grade level</th>
                            <th class="px-4 py-3">Adviser</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Usage</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @forelse ($sections as $section)
                            <tr>
                                <td class="px-4 py-4 font-semibold text-slate-900">{{ $section->name }}</td>
                                <td class="px-4 py-4">{{ $section->schoolYear->name }}</td>
                                <td class="px-4 py-4">{{ $section->gradeLevel->name }}</td>
                                <td class="px-4 py-4 text-slate-500">{{ $section->adviser?->name ?? 'Unassigned' }}</td>
                                <td class="px-4 py-4">
                                    <x-status-chip :tone="$section->is_active ? 'emerald' : 'slate'">
                                        {{ $section->is_active ? 'Active' : 'Inactive' }}
                                    </x-status-chip>
                                </td>
                                <td class="px-4 py-4 text-slate-500">
                                    {{ $section->teacher_loads_count }} loads, {{ $section->section_rosters_count }} rosters
                                </td>
                                <td class="px-4 py-4">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('admin.academic-setup.sections.show', $section)"
                                            icon="eye"
                                            title="View section"
                                            aria-label="View section"
                                        >
                                            View
                                        </x-table-action-button>
                                        @can('update', $section)
                                            <x-table-action-button
                                                :href="route('admin.academic-setup.sections.edit', $section)"
                                                icon="edit"
                                                title="Edit section"
                                                aria-label="Edit section"
                                            >
                                                Edit
                                            </x-table-action-button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No sections matched the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-4">
                {{ $sections->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
