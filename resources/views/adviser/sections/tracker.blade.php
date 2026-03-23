<x-app-layout>
    <x-slot name="header">
        <x-page-header
            eyebrow="Adviser review"
            :title="$section->gradeLevel->name.' · '.$section->name"
            :description="'Subject submission tracker for '.$gradingPeriod->quarter->label().' in '.$section->schoolYear->name.'. Only approved subject submissions make this section ready for finalization.'"
        >
            <x-slot name="actions">
                <a href="{{ route('adviser.sections.consolidation.learners', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="ui-link-button">
                    Consolidate by learner
                </a>
                <a href="{{ route('adviser.sections.index', ['school_year_id' => $section->school_year_id, 'grading_period_id' => $gradingPeriod->id]) }}" class="ui-link-button">
                    Back to sections
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="page-stack">
        @include('admin.academic-setup.partials.flash')
        @include('adviser.partials.navigation')
        @include('adviser.partials.readiness-panel', ['summary' => $summary])

        <x-filter-bar title="Submission tracker filters" description="Search by teacher or subject and focus the tracker on the workflow states that still need adviser attention.">
            <form method="GET" action="{{ route('adviser.sections.tracker', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_auto]">
                <div>
                    <x-input-label for="search" value="Search teacher or subject" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Teacher name, subject, or code" />
                </div>

                <div>
                    <x-input-label for="status" value="Workflow status" />
                    <select id="status" name="status" class="ui-select mt-1">
                        @foreach ($statusOptions as $option)
                            <option value="{{ $option['value'] }}" @selected($filters['status'] === $option['value'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="action-bar items-end">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('adviser.sections.tracker', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="ui-link-button">
                        Reset
                    </a>
                </div>
            </form>
        </x-filter-bar>

        <x-table-wrapper title="Subject submission tracker" description="Each row represents an assigned subject load for this advisory section and quarter." :count="$teacherLoads->total().' submission row'.($teacherLoads->total() === 1 ? '' : 's')">
            <x-slot name="actions">
                <x-status-chip tone="slate">{{ $gradingPeriod->quarter->label() }}</x-status-chip>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Status</th>
                            <th>Timestamps</th>
                            <th>Remarks</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($teacherLoads as $teacherLoad)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $teacherLoad['subject_name'] }}</p>
                                    <p class="table-support">{{ $teacherLoad['subject_code'] }} · {{ $teacherLoad['teacher_name'] }}</p>
                                </td>
                                <td>
                                    <x-status-chip :tone="$teacherLoad['status']['tone']">
                                        {{ $teacherLoad['status']['label'] }}
                                    </x-status-chip>
                                </td>
                                <td class="text-slate-600">
                                    <p>Submitted: {{ $teacherLoad['submitted_at'] ?? 'Not yet' }}</p>
                                    <p class="table-support">Returned: {{ $teacherLoad['returned_at'] ?? 'Not yet' }}</p>
                                    <p class="table-support">Approved: {{ $teacherLoad['approved_at'] ?? 'Not yet' }}</p>
                                </td>
                                <td class="text-slate-600">
                                    {{ $teacherLoad['adviser_remarks'] ?: 'No adviser remarks recorded.' }}
                                </td>
                                <td class="text-right">
                                    <div class="table-row-actions ml-auto w-fit">
                                        @if ($teacherLoad['submission_id'] !== null)
                                            <x-table-action-button
                                                :href="route('adviser.sections.submissions.show', ['section' => $section, 'grading_period' => $gradingPeriod, 'grade_submission' => $teacherLoad['submission_id']])"
                                                icon="eye"
                                                title="Review submission"
                                                aria-label="Review submission"
                                            >
                                                Review
                                            </x-table-action-button>
                                        @else
                                            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Awaiting teacher</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state title="No subject submissions matched these filters." description="Try widening the search term or clearing the current workflow-status filter." />
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
