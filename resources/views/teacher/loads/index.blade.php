<x-app-layout>
    <x-slot name="header">
        <x-page-header
            eyebrow="Teacher workspace"
            title="My Teaching Loads"
            description="Review your assigned sections and subjects, then open each load to see the official learner list."
        >
            <x-slot name="actions">
                <a href="{{ route('teacher.returned-submissions.index') }}" class="ui-link-button">
                    View returned submissions
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="page-stack">
        @include('admin.academic-setup.partials.flash')
        @include('teacher.partials.navigation')

        <x-filter-bar title="Find teaching loads" description="Filter by school year, load activity, or a subject and section keyword.">
            <form method="GET" action="{{ route('teacher.loads.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_240px_180px_auto]">
                <div>
                    <x-input-label for="search" value="Search" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Subject, section, school year, or adviser" />
                </div>

                <div>
                    <x-input-label for="school_year_id" value="School year" />
                    <select id="school_year_id" name="school_year_id" class="ui-select mt-1">
                        <option value="">All school years</option>
                        @foreach ($schoolYears as $schoolYear)
                            <option value="{{ $schoolYear->id }}" @selected($filters['school_year_id'] === $schoolYear->id)>
                                {{ $schoolYear->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="ui-select mt-1">
                        <option value="">All statuses</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="action-bar items-end">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('teacher.loads.index') }}" class="ui-link-button">
                        Reset
                    </a>
                </div>
            </form>
        </x-filter-bar>

        <x-table-wrapper title="Assigned loads" description="Only teaching loads officially assigned to your account are listed here." :count="$teacherLoads->total().' total load'.($teacherLoads->total() === 1 ? '' : 's')">
            <x-slot name="actions">
                <x-status-chip tone="slate">{{ $teacherLoads->total() }} listed</x-status-chip>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Section support</th>
                            <th>Status</th>
                            <th>Returned items</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($teacherLoads as $teacherLoad)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $teacherLoad->subject->name }}</p>
                                    <p class="table-support">
                                        {{ $teacherLoad->subject->code }} · {{ $teacherLoad->schoolYear->name }}
                                    </p>
                                    <p class="table-support">
                                        {{ $teacherLoad->section->gradeLevel->name }} · {{ $teacherLoad->section->name }}
                                    </p>
                                </td>
                                <td class="text-slate-600">
                                    {{ $teacherLoad->section->adviser?->name ?? 'No adviser assigned' }}
                                </td>
                                <td>
                                    <x-status-chip :state="$teacherLoad->is_active ? 'active' : 'inactive'">
                                        {{ $teacherLoad->is_active ? 'Active' : 'Inactive' }}
                                    </x-status-chip>
                                </td>
                                <td class="text-slate-600">
                                    {{ $teacherLoad->returned_submissions_count }} returned
                                </td>
                                <td class="text-right">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('teacher.loads.show', $teacherLoad)"
                                            icon="users"
                                            title="View learners"
                                            aria-label="View learners"
                                        >
                                            Learners
                                        </x-table-action-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state title="No teaching loads matched these filters." description="Try a different keyword, switch the school year, or reset the status filter." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-slot name="footer">
                {{ $teacherLoads->links() }}
            </x-slot>
        </x-table-wrapper>
    </div>
</x-app-layout>
