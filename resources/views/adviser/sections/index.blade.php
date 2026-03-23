<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Adviser workspace</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Advisory Sections</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Review each advisory section by grading period, track missing subject submissions, and open approved-only consolidation views.
                </p>
            </div>

            <a href="{{ route('adviser.dashboard', ['school_year_id' => $filters['school_year_id'], 'grading_period_id' => $filters['grading_period_id']]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                Back to dashboard
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('adviser.partials.navigation')

        <section class="content-card">
            <form method="GET" action="{{ route('adviser.sections.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_260px_auto]">
                <div>
                    <x-input-label for="search" value="Search sections" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Section, grade level, or school year" />
                </div>

                <div>
                    <x-input-label for="school_year_id" value="School year" />
                    <select id="school_year_id" name="school_year_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">Select school year</option>
                        @foreach ($availableSchoolYears as $schoolYear)
                            <option value="{{ $schoolYear->id }}" @selected($filters['school_year_id'] === $schoolYear->id)>
                                {{ $schoolYear->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="grading_period_id" value="Grading period" />
                    <select id="grading_period_id" name="grading_period_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">Select grading period</option>
                        @foreach ($availableGradingPeriods as $gradingPeriod)
                            <option value="{{ $gradingPeriod->id }}" @selected($filters['grading_period_id'] === $gradingPeriod->id)>
                                {{ $gradingPeriod->quarter->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('adviser.sections.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
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
                            <th class="px-4 py-3">Quarter status</th>
                            <th class="px-4 py-3">Progress</th>
                            <th class="px-4 py-3">Blockers</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @forelse ($sections as $section)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $section['grade_level_name'] }} · {{ $section['section_name'] }}</p>
                                    <p class="mt-1 text-slate-500">{{ $section['school_year_name'] }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <x-status-chip :tone="$section['status']['tone']">
                                            {{ $section['status']['label'] }}
                                        </x-status-chip>
                                        <x-status-chip tone="rose">Missing: {{ $section['missing_submission_count'] }}</x-status-chip>
                                        <x-status-chip tone="amber">Returned: {{ $section['returned_submission_count'] }}</x-status-chip>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $section['completion_percentage'] }}%</p>
                                    <p class="mt-1 text-slate-500">
                                        {{ $section['approved_submission_count'] }} approved of {{ $section['expected_submission_count'] }} expected
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    {{ $section['blockers'][0] ?? 'No blockers recorded.' }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="table-row-actions ml-auto w-fit">
                                        @if ($selectedGradingPeriod !== null)
                                            <x-table-action-button
                                                :href="route('adviser.sections.tracker', ['section' => $section['section_id'], 'grading_period' => $selectedGradingPeriod])"
                                                icon="monitor"
                                                title="Open section tracker"
                                                aria-label="Open section tracker"
                                            >
                                                Tracker
                                            </x-table-action-button>
                                            <x-table-action-button
                                                :href="route('adviser.sections.consolidation.subjects', ['section' => $section['section_id'], 'grading_period' => $selectedGradingPeriod])"
                                                icon="book"
                                                title="Open subject consolidation"
                                                aria-label="Open subject consolidation"
                                            >
                                                Subjects
                                            </x-table-action-button>
                                        @endif
                                        <x-table-action-button
                                            :href="route('adviser.sections.learner-movements.index', ['section' => $section['section_id']])"
                                            icon="users"
                                            title="Manage learner exceptions"
                                            aria-label="Manage learner exceptions"
                                        >
                                            Exceptions
                                        </x-table-action-button>
                                        <x-table-action-button
                                            :href="route('adviser.sections.year-end.index', ['section' => $section['section_id']])"
                                            icon="archive"
                                            title="Open year-end preparation"
                                            aria-label="Open year-end preparation"
                                        >
                                            Year-end
                                        </x-table-action-button>
                                        @if ($selectedGradingPeriod === null)
                                            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Select a grading period first for quarter review</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No advisory sections matched the current filters.
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
