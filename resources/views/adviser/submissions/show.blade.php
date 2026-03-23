<x-app-layout>
    <x-slot name="header">
        <x-page-header
            eyebrow="Adviser review"
            :title="$submission['subject_name']"
            :description="'Read-only submission review for '.$section->gradeLevel->name.' · '.$section->name.' during '.$gradingPeriod->quarter->label().'. Advisers can approve or return, but they cannot edit teacher grade values here.'"
        >
            <x-slot name="actions">
                <a href="{{ route('adviser.sections.tracker', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="ui-link-button">
                    Back to tracker
                </a>
                <a href="{{ route('adviser.sections.consolidation.subjects', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="ui-link-button">
                    View subject consolidation
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="page-stack">
        @include('admin.academic-setup.partials.flash')
        @include('adviser.partials.navigation')
        @include('adviser.partials.readiness-panel', ['summary' => $summary])

        <section class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
            <x-section-panel title="Submission details">
                <x-slot name="actions">
                    <x-status-chip :tone="$submission['status']['tone']">
                        {{ $submission['status']['label'] }}
                    </x-status-chip>
                </x-slot>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Teacher</dt>
                        <dd class="mt-2 text-sm text-slate-900">{{ $submission['teacher_name'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Subject code</dt>
                        <dd class="mt-2 text-sm text-slate-900">{{ $submission['subject_code'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Submitted at</dt>
                        <dd class="mt-2 text-sm text-slate-900">{{ $submission['submitted_at'] ?? 'Not yet submitted' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Last updated</dt>
                        <dd class="mt-2 text-sm text-slate-900">{{ $submission['updated_at'] ?? 'Not available' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Returned at</dt>
                        <dd class="mt-2 text-sm text-slate-900">{{ $submission['returned_at'] ?? 'Not returned' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Approved at</dt>
                        <dd class="mt-2 text-sm text-slate-900">{{ $submission['approved_at'] ?? 'Not approved' }}</dd>
                    </div>
                </dl>

                @if ($submission['adviser_remarks'])
                    <div class="mt-6">
                        <x-alert-panel tone="amber" title="Latest adviser remarks" icon="undo">
                            {{ $submission['adviser_remarks'] }}
                        </x-alert-panel>
                    </div>
                @endif
            </x-section-panel>

            <x-section-panel title="Review action">
                @if ($submission['review_block_message'])
                    <x-blocker-panel title="Decision blocked">
                        {{ $submission['review_block_message'] }}
                    </x-blocker-panel>
                @endif

                @if ($submission['is_decision_allowed'])
                    <div class="space-y-4">
                        <form method="POST" action="{{ route('adviser.sections.submissions.approve', ['section' => $section, 'grading_period' => $gradingPeriod, 'grade_submission' => $submission['id']]) }}">
                            @csrf

                            <x-primary-button class="w-full justify-center">
                                Approve submission
                            </x-primary-button>
                        </form>

                        <form method="POST" action="{{ route('adviser.sections.submissions.return', ['section' => $section, 'grading_period' => $gradingPeriod, 'grade_submission' => $submission['id']]) }}" class="space-y-4">
                            @csrf

                            <div>
                                <x-input-label for="remarks" value="Return remarks" />
                                <textarea id="remarks" name="remarks" rows="5" class="ui-textarea mt-1" placeholder="State the correction needed before resubmission.">{{ old('remarks') }}</textarea>
                                <x-input-error class="mt-2" :messages="$errors->get('remarks')" />
                                <x-input-error class="mt-2" :messages="$errors->get('submission')" />
                            </div>

                            <button type="submit" class="ui-button ui-button--warning w-full">
                                Return for correction
                            </button>
                        </form>
                    </div>
                @endif
            </x-section-panel>
        </section>

        <x-table-wrapper title="Official learner grades" description="This table is read-only and limited to official roster learners for the section and school year tied to this submission.">
            <x-slot name="actions">
                <x-status-chip :tone="$submission['status']['tone']">
                    {{ $submission['status']['label'] }}
                </x-status-chip>
            </x-slot>

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
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $row['learner_name'] }}</p>
                                    <p class="table-support">{{ $row['lrn'] }}</p>
                                </td>
                                <td>
                                    <x-status-chip :tone="$row['enrollment_status']['tone']">
                                        {{ $row['enrollment_status']['label'] }}
                                    </x-status-chip>
                                    <p class="table-note">{{ $row['eligibility_note'] }}</p>
                                </td>
                                <td class="text-slate-900">
                                    {{ $row['grade'] ?? '—' }}
                                </td>
                                <td class="text-slate-600">
                                    {{ $row['remarks'] ?? ($row['accepts_grade'] ? 'No recorded remark.' : 'Learner is not grade-eligible for this quarter.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-table-wrapper>

        <x-table-wrapper title="Audit trail" description="Adviser review actions are logged here with actor, action, timestamp, and remarks where applicable.">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>User</th>
                            <th>Timestamp</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($approvalLogs as $log)
                            <tr>
                                <td class="font-semibold text-slate-900">{{ $log['label'] }}</td>
                                <td>{{ $log['acted_by'] }}</td>
                                <td class="text-slate-500">{{ $log['created_at'] ?? 'Not available' }}</td>
                                <td class="text-slate-600">{{ $log['remarks'] ?: 'No remarks recorded.' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-empty-state title="No audit entries are recorded yet." description="Review actions will appear here once the submission has been returned or approved." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-table-wrapper>
    </div>
</x-app-layout>
