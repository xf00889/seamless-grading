<div class="space-y-6">
    @if ($feedbackMessage)
        <x-alert-panel :tone="$feedbackTone" title="Grade entry update" icon="dashboard">
            {{ $feedbackMessage }}
        </x-alert-panel>
    @endif

    @error('form.record')
        <x-blocker-panel tone="rose" title="Workflow blocked">
            {{ $message }}
        </x-blocker-panel>
    @enderror

    <section class="grid gap-4 xl:grid-cols-[1.5fr_1fr_1fr]">
        <x-card>
            <div class="flex flex-wrap items-center gap-3">
                <x-status-chip :state="$workflow['status']['value']">
                    {{ $workflow['status']['label'] }}
                </x-status-chip>
                <x-status-chip :state="$loadSummary['is_active'] ? 'active' : 'inactive'">
                    {{ $loadSummary['is_active'] ? 'Active load' : 'Inactive load' }}
                </x-status-chip>
                <x-status-chip :state="$gradingPeriodSummary['is_open'] ? 'active' : 'inactive'">
                    {{ $gradingPeriodSummary['is_open'] ? 'Open period' : 'Closed period' }}
                </x-status-chip>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Assignment</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $loadSummary['subject_name'] }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $loadSummary['subject_code'] }} · {{ $loadSummary['section_name'] }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $loadSummary['grade_level_name'] }} · {{ $loadSummary['school_year_name'] }}</p>
                </div>

                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Period</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $gradingPeriodSummary['quarter_label'] }}</p>
                    <p class="mt-1 text-sm text-slate-500">Adviser: {{ $loadSummary['adviser_name'] }}</p>
                    @if ($gradingPeriodSummary['starts_on'] || $gradingPeriodSummary['ends_on'])
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $gradingPeriodSummary['starts_on'] ?? 'TBD' }} to {{ $gradingPeriodSummary['ends_on'] ?? 'TBD' }}
                        </p>
                    @endif
                </div>
            </div>

            @if ($workflow['adviser_remarks'])
                <div class="mt-4">
                    <x-alert-panel tone="amber" title="Returned remarks" icon="undo">
                        {{ $workflow['adviser_remarks'] }}
                    </x-alert-panel>
                </div>
            @endif

            @if ($workflow['block_message'])
                <div class="mt-4">
                    <x-blocker-panel tone="slate" title="Editing notice">
                        {{ $workflow['block_message'] }}
                    </x-blocker-panel>
                </div>
            @endif
        </x-card>

        <x-card>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Grading rules</p>
            <dl class="mt-4 space-y-3 text-sm text-slate-600">
                <div class="flex items-center justify-between gap-4">
                    <dt>Valid range</dt>
                    <dd class="font-semibold text-slate-900">{{ $gradingRules['minimum'] }} to {{ $gradingRules['maximum'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt>Passing grade</dt>
                    <dd class="font-semibold text-slate-900">{{ $gradingRules['passing'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt>Decimal places</dt>
                    <dd class="font-semibold text-slate-900">{{ $gradingRules['decimal_places'] }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt>Draft blanks</dt>
                    <dd class="font-semibold text-slate-900">{{ $gradingRules['allow_blank_in_drafts'] ? 'Allowed' : 'Blocked' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Submission timing</p>
            <dl class="mt-4 space-y-3 text-sm text-slate-600">
                <div class="flex items-center justify-between gap-4">
                    <dt>Last updated</dt>
                    <dd class="text-right font-semibold text-slate-900">{{ $workflow['updated_at'] ?? 'Not saved yet' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt>Submitted</dt>
                    <dd class="text-right font-semibold text-slate-900">{{ $workflow['submitted_at'] ?? 'Not submitted yet' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt>Returned</dt>
                    <dd class="text-right font-semibold text-slate-900">{{ $workflow['returned_at'] ?? 'Not returned' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt>Locked</dt>
                    <dd class="text-right font-semibold text-slate-900">{{ $workflow['locked_at'] ?? 'Not locked' }}</dd>
                </div>
            </dl>
        </x-card>
    </section>

    <x-section-panel
        title="Quarterly Grades"
        description="Save drafts as you work, then submit only when every learner who is still grade-eligible for this quarter has a valid grade."
    >
        <x-slot name="actions">
            <div class="action-bar">
                <span wire:dirty wire:target="form.grades" class="status-chip status-chip--draft">
                    <span class="status-chip__dot" aria-hidden="true"></span>
                    <span>Unsaved changes</span>
                </span>

                <button
                    type="button"
                    wire:click="saveDraft"
                    wire:loading.attr="disabled"
                    wire:target="saveDraft,submitGrades"
                    class="ui-button ui-button--secondary"
                    @disabled(! $workflow['is_editable'])
                >
                    {{ $workflow['status']['value'] === 'returned' ? 'Save corrections' : 'Save draft' }}
                </button>

                <button
                    type="button"
                    wire:click="submitGrades"
                    wire:loading.attr="disabled"
                    wire:target="saveDraft,submitGrades"
                    class="ui-button ui-button--primary"
                    @disabled(! $workflow['is_editable'])
                >
                    {{ $workflow['status']['value'] === 'returned' ? 'Resubmit grades' : 'Submit grades' }}
                </button>
            </div>
        </x-slot>
    </x-section-panel>

    <x-table-wrapper>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            @if (! $workflow['is_editable'])
                <x-blocker-panel title="Read-only workflow state">
                    Submitted, approved, or locked submissions cannot be edited here until they return to a teacher-correctable state.
                </x-blocker-panel>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Learner</th>
                        <th>Enrollment</th>
                        <th>Grade</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="grade-row-{{ $row['section_roster_id'] }}">
                            <td>
                                <p class="font-semibold text-slate-900">{{ $row['learner_name'] }}</p>
                                <p class="table-support">LRN {{ $row['lrn'] }} · {{ $row['sex'] }}</p>
                            </td>
                            <td>
                                <x-status-chip :tone="$row['enrollment_status']['tone']">
                                    {{ $row['enrollment_status']['label'] }}
                                </x-status-chip>
                                <p class="table-note max-w-xs">{{ $row['grade_note'] }}</p>
                            </td>
                            <td>
                                <div class="max-w-[11rem]">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="{{ $gradingRules['minimum'] }}"
                                        max="{{ $gradingRules['maximum'] }}"
                                        wire:model.blur="form.grades.{{ $row['section_roster_id'] }}.grade"
                                        @disabled(! $workflow['is_editable'] || ! $row['accepts_grade'])
                                        class="ui-input"
                                    />
                                    @error('form.grades.'.$row['section_roster_id'].'.grade')
                                        <p class="ui-error-list">{{ $message }}</p>
                                    @enderror
                                </div>
                            </td>
                            <td class="text-slate-600">
                                {{ $row['remarks'] ?? 'Will be calculated after save or submit.' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-table-wrapper>

    <x-section-panel>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm leading-6 text-slate-500">
                Enter grades carefully. Submitted records become read-only until they are returned for correction.
            </p>

            <div class="action-bar action-bar--sticky">
                <button
                    type="button"
                    wire:click="saveDraft"
                    wire:loading.attr="disabled"
                    wire:target="saveDraft,submitGrades"
                    class="ui-button ui-button--secondary"
                    @disabled(! $workflow['is_editable'])
                >
                    {{ $workflow['status']['value'] === 'returned' ? 'Save corrections' : 'Save draft' }}
                </button>

                <button
                    type="button"
                    wire:click="submitGrades"
                    wire:loading.attr="disabled"
                    wire:target="saveDraft,submitGrades"
                    class="ui-button ui-button--primary"
                    @disabled(! $workflow['is_editable'])
                >
                    {{ $workflow['status']['value'] === 'returned' ? 'Resubmit grades' : 'Submit grades' }}
                </button>
            </div>
        </div>
    </x-section-panel>
</div>
