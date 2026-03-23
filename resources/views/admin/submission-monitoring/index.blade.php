<x-app-layout>
    <x-slot name="header">
        <x-page-header
            eyebrow="Admin tools"
            title="Submission Monitoring"
            description="Monitor quarter progress, spot missing or late submissions, and explicitly lock or reopen completed section records without bypassing the adviser review flow."
        />
    </x-slot>

    <div class="page-stack">
        @include('admin.academic-setup.partials.flash')
        @if ($errors->any() && ! $errors->has('record'))
            <x-alert-panel tone="rose" title="Validation issue">
                {{ $errors->first() }}
            </x-alert-panel>
        @endif
        @include('admin.submission-monitoring.partials.navigation')

        <x-filter-bar title="Monitoring filters" description="Slice the monitoring view by academic context, ownership, or workflow state without changing the core readiness calculations.">
            <form method="GET" action="{{ route('admin.submission-monitoring') }}" class="grid gap-4 lg:grid-cols-4">
                <label class="block">
                    <span class="ui-label">Search</span>
                    <input
                        type="text"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Section, subject, teacher, or adviser"
                        class="ui-input mt-2"
                    />
                </label>

                <label class="block">
                    <span class="ui-label">School Year</span>
                    <select name="school_year_id" class="ui-select mt-2">
                        @foreach ($availableSchoolYears as $schoolYear)
                            <option value="{{ $schoolYear->id }}" @selected($filters['school_year_id'] === $schoolYear->id)>
                                {{ $schoolYear->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="ui-label">Grading Period</span>
                    <select name="grading_period_id" class="ui-select mt-2">
                        @forelse ($availableGradingPeriods as $gradingPeriod)
                            <option value="{{ $gradingPeriod->id }}" @selected($filters['grading_period_id'] === $gradingPeriod->id)>
                                {{ $gradingPeriod->quarter->label() }}
                            </option>
                        @empty
                            <option value="">No grading periods</option>
                        @endforelse
                    </select>
                </label>

                <label class="block">
                    <span class="ui-label">Section</span>
                    <select name="section_id" class="ui-select mt-2">
                        <option value="">All sections</option>
                        @foreach ($availableSections as $section)
                            <option value="{{ $section->id }}" @selected($filters['section_id'] === $section->id)>
                                {{ $section->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="ui-label">Adviser</span>
                    <select name="adviser_id" class="ui-select mt-2">
                        <option value="">All advisers</option>
                        @foreach ($availableAdvisers as $adviser)
                            <option value="{{ $adviser->id }}" @selected($filters['adviser_id'] === $adviser->id)>
                                {{ $adviser->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="ui-label">Teacher</span>
                    <select name="teacher_id" class="ui-select mt-2">
                        <option value="">All teachers</option>
                        @foreach ($availableTeachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === $teacher->id)>
                                {{ $teacher->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="ui-label">Submission Status</span>
                    <select name="status" class="ui-select mt-2">
                        @foreach ($statusOptions as $option)
                            <option value="{{ $option['value'] }}" @selected($filters['status'] === $option['value'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <div class="action-bar items-end">
                    <button type="submit" class="ui-button ui-button--primary">
                        Apply filters
                    </button>
                    <a href="{{ route('admin.submission-monitoring') }}" class="ui-link-button">
                        Reset
                    </a>
                </div>
            </form>
        </x-filter-bar>

        <div class="stats-grid submission-monitoring__metrics xl:grid-cols-3">
            @foreach ($summaryCards as $card)
                <x-dashboard.metric-card
                    :label="$card['label']"
                    :value="$card['value']"
                    :icon="$card['icon']"
                    :tone="$card['tone']"
                    :status="$card['status']"
                    :status-tone="$card['status_tone']"
                />
            @endforeach
        </div>

        @if ($selectedGradingPeriod && $totals['late_submissions'] > 0)
            <x-blocker-panel title="Late submission watch">
                The current monitoring view is surfacing {{ $totals['late_submissions'] }} late submission item(s) against the
                {{ $selectedGradingPeriod->quarter->label() }} deadline of {{ $selectedGradingPeriod->ends_on?->format('M d, Y') ?? 'Not set' }}.
            </x-blocker-panel>
        @endif

        <x-table-wrapper
            title="Section Quarter Status"
            description="Locking is allowed only when every active subject load for the section is approved and every learner who is still grade-eligible in the selected grading period has a finalized SF9 record."
        >
            <x-slot name="actions">
                @if ($selectedGradingPeriod)
                    <x-status-chip state="submitted">
                        Deadline {{ $selectedGradingPeriod->ends_on?->format('M d, Y') ?? 'Not set' }}
                    </x-status-chip>
                @endif
            </x-slot>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th>Progress</th>
                            <th>SF9 Finalization</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sections as $section)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $section['section_name'] }}</p>
                                    <p class="table-support">{{ $section['grade_level_name'] }} | {{ $section['school_year_name'] }}</p>
                                    <p class="table-note">Adviser: {{ $section['adviser_name'] }}</p>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $section['completion_percentage'] }}%</p>
                                    <p class="table-support">
                                        {{ $section['approved_submission_count'] + $section['locked_submission_count'] }}
                                        of {{ $section['expected_submission_count'] }} subject load(s) ready
                                    </p>
                                    <p class="table-note">
                                        Missing: {{ $section['missing_submission_count'] }},
                                        Draft: {{ $section['draft_submission_count'] }},
                                        Submitted: {{ $section['submitted_submission_count'] }},
                                        Returned: {{ $section['returned_submission_count'] }},
                                        Locked: {{ $section['locked_submission_count'] }}
                                    </p>
                                    @if ($section['late_submission_count'] > 0)
                                        <p class="mt-2 text-sm font-semibold text-amber-700">{{ $section['late_submission_count'] }} late submission item(s)</p>
                                    @endif
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $section['finalized_sf9_count'] }} / {{ $section['required_sf9_roster_count'] }}</p>
                                    <p class="table-support">Grade-eligible learner records finalized for SF9.</p>
                                    @if ($section['lock_blockers'] !== [])
                                        <p class="table-note">{{ implode(' ', $section['lock_blockers']) }}</p>
                                    @endif
                                </td>
                                <td>
                                    <x-status-chip :tone="$section['status']['tone']" class="submission-monitoring__section-status">
                                        {{ $section['status']['label'] }}
                                    </x-status-chip>
                                </td>
                                <td>
                                    @php
                                        $reopenModalName = 'reopen-quarter-records-'.$section['model']->getKey();
                                        $shouldAutoOpenReopenModal = $errors->has('reason')
                                            && (string) old('reopen_section_id') === (string) $section['model']->getKey();
                                    @endphp

                                    <div class="table-row-actions submission-monitoring__table-actions">
                                        <x-table-action-button
                                            :href="route('admin.submission-monitoring.sections.learner-movements.index', ['section' => $section['model']])"
                                            icon="users"
                                            title="Manage learner exceptions"
                                            aria-label="Manage learner exceptions"
                                        >
                                            Exceptions
                                        </x-table-action-button>
                                        @can('lockQuarterAsAdmin', $section['model'])
                                            @if ($section['can_lock'] && $selectedGradingPeriod)
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.submission-monitoring.sections.lock', ['section' => $section['model'], 'grading_period' => $selectedGradingPeriod]) }}"
                                                    data-confirm-message="Lock this section quarter? Locked submissions will stay blocked until an authorized admin reopens them."
                                                    class="table-row-actions__form submission-monitoring__table-action-form"
                                                >
                                                    @csrf
                                                    <x-table-action-button
                                                        type="submit"
                                                        tone="primary"
                                                        icon="lock"
                                                        class="submission-monitoring__lock-action"
                                                        title="Lock quarter records"
                                                        aria-label="Lock quarter records"
                                                    >
                                                        Lock
                                                    </x-table-action-button>
                                                </form>
                                            @endif
                                        @endcan

                                        @can('reopenQuarterAsAdmin', $section['model'])
                                            @if ($section['can_reopen'] && $selectedGradingPeriod)
                                                <x-table-action-button
                                                    type="button"
                                                    tone="warning"
                                                    icon="undo"
                                                    class="submission-monitoring__reopen-action"
                                                    :data-modal-open="$reopenModalName"
                                                    title="Reopen quarter records"
                                                    aria-label="Reopen quarter records"
                                                >
                                                    Reopen
                                                </x-table-action-button>

                                                <x-ui-dialog
                                                    :name="$reopenModalName"
                                                    max-width="xl"
                                                    :data-modal-auto-open="$shouldAutoOpenReopenModal ? 'true' : 'false'"
                                                >
                                                    <div class="ui-dialog__header">
                                                        <div>
                                                            <h3 class="ui-dialog__title">Reopen section quarter</h3>
                                                            <p class="ui-dialog__description">
                                                                Return locked records for {{ $section['section_name'] }} to the review path for {{ $selectedGradingPeriod->quarter->label() }}.
                                                                This will invalidate finalized learner report-card records tied to this quarter.
                                                            </p>
                                                        </div>

                                                        <button type="button" class="ui-dialog__close" data-modal-close aria-label="Close reopen quarter dialog">
                                                            <x-icon name="close" class="h-4 w-4" />
                                                        </button>
                                                    </div>

                                                    <form
                                                        method="POST"
                                                        action="{{ route('admin.submission-monitoring.sections.reopen', ['section' => $section['model'], 'grading_period' => $selectedGradingPeriod]) }}"
                                                    >
                                                        @csrf
                                                        <input type="hidden" name="reopen_section_id" value="{{ $section['model']->getKey() }}" />

                                                        <div class="ui-dialog__body">
                                                            <label class="block">
                                                                <span class="ui-label">Reason for reopening</span>
                                                                <textarea
                                                                    name="reason"
                                                                    rows="4"
                                                                    class="ui-textarea mt-2"
                                                                    placeholder="Explain why the locked records must re-enter review."
                                                                    data-modal-initial-focus
                                                                >{{ $shouldAutoOpenReopenModal ? old('reason') : '' }}</textarea>
                                                            </label>

                                                            @if ($shouldAutoOpenReopenModal)
                                                                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                                                            @endif

                                                            <p class="ui-help">
                                                                Reopened submissions go back through correction, resubmission, adviser review, and approval before they can be locked again.
                                                            </p>
                                                        </div>

                                                        <div class="ui-dialog__footer">
                                                            <button type="button" class="ui-link-button" data-modal-close>
                                                                Cancel
                                                            </button>
                                                            <button type="submit" class="ui-button ui-button--warning">
                                                                <x-icon name="undo" class="h-4 w-4" />
                                                                <span>Reopen</span>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </x-ui-dialog>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                                    No sections matched the current school-year, adviser, or search filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($sections->hasPages())
                <x-slot name="footer">
                    {{ $sections->links() }}
                </x-slot>
            @endif
        </x-table-wrapper>

        <x-table-wrapper
            title="Submission Row Monitor"
            description="Teacher and status filters apply to this table so you can drill into specific loads without changing the full section-lock readiness calculation above."
        >
            <x-slot name="actions">
                <a href="{{ route('admin.submission-monitoring.audit') }}" class="ui-link-button">
                    Open audit log
                </a>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Section</th>
                            <th>Teacher</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Timing</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($submissionRows as $row)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $row['section_name'] }}</p>
                                    <p class="table-support">{{ $row['grade_level_name'] }}</p>
                                    <p class="table-note">Adviser: {{ $row['adviser_name'] }}</p>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $row['teacher_name'] }}</p>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $row['subject_name'] }}</p>
                                    <p class="table-support">{{ $row['subject_code'] }}</p>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        <x-status-chip :tone="$row['status']['tone']">
                                            {{ $row['status']['label'] }}
                                        </x-status-chip>
                                        @if ($row['was_reopened'])
                                            <x-status-chip state="returned">Reopened</x-status-chip>
                                        @endif
                                        @if ($row['is_late'])
                                            <x-status-chip state="blocked">Late</x-status-chip>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-slate-600">
                                    <p>Submitted: {{ $row['submitted_at'] ?? 'Not submitted' }}</p>
                                    <p class="table-support">Returned: {{ $row['returned_at'] ?? 'Not returned' }}</p>
                                    <p class="table-support">Approved: {{ $row['approved_at'] ?? 'Not approved' }}</p>
                                    <p class="table-support">Locked: {{ $row['locked_at'] ?? 'Not locked' }}</p>
                                </td>
                                <td class="text-slate-600">
                                    <p>{{ $row['late_reason'] ?? 'No deadline issue recorded.' }}</p>
                                    @if (filled($row['adviser_remarks']))
                                        <p class="table-note">Remarks: {{ $row['adviser_remarks'] }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty-state title="No submission rows matched these filters." description="Try adjusting the teacher, section, or workflow-status filters to widen the monitoring result set." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($submissionRows->hasPages())
                <x-slot name="footer">
                    {{ $submissionRows->links() }}
                </x-slot>
            @endif
        </x-table-wrapper>
    </div>
</x-app-layout>
