<x-app-layout>
    <x-slot name="header">
        <x-page-header
            eyebrow="Adviser year-end"
            title="SF10 Preparation"
            description="Review the approved full-year subject data, the latest SF10 draft version, and the explicit finalization state before handing the record off to the registrar repository."
        >
            <x-slot name="actions">
                <a href="{{ route('adviser.sections.year-end.index', ['section' => $section]) }}" class="ui-link-button">
                    Back to learner statuses
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="page-stack">
        @include('admin.academic-setup.partials.flash')
        @if ($errors->has('record'))
            <x-blocker-panel tone="rose" title="SF10 blocked">
                {{ $errors->first('record') }}
            </x-blocker-panel>
        @endif
        @include('adviser.partials.navigation')

        <section class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <x-section-panel title="Learner year-end context">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ $year_end['learner']['name'] }}</h2>
                        <p class="mt-2 text-sm text-slate-600">LRN: {{ $year_end['learner']['lrn'] }}</p>
                    </div>

                    @if ($year_end['year_end_status'] !== null)
                        <x-status-chip :tone="$year_end['year_end_status']['tone']">
                            {{ $year_end['year_end_status']['label'] }}
                        </x-status-chip>
                    @else
                        <x-status-chip tone="slate">Status not set</x-status-chip>
                    @endif
                </div>

                <dl class="mt-6 grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Section</dt>
                        <dd class="mt-2 text-sm text-slate-700">{{ $section->gradeLevel->name }} · {{ $section->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">School Year</dt>
                        <dd class="mt-2 text-sm text-slate-700">{{ $section->schoolYear->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Adviser</dt>
                        <dd class="mt-2 text-sm text-slate-700">{{ $section->adviser?->name ?? 'Unassigned' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Final Grading Period</dt>
                        <dd class="mt-2 text-sm text-slate-700">{{ $year_end['final_grading_period_label'] ?? 'Not configured' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">General Average</dt>
                        <dd class="mt-2 text-sm text-slate-700">{{ $year_end['general_average'] ?? 'Pending approved full-year data' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Template Version</dt>
                        <dd class="mt-2 text-sm text-slate-700">
                            @if ($template !== null)
                                {{ $template['name'] }} · v{{ $template['version'] }}
                            @else
                                No active SF10 template
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($year_end['enrollment_context']['effective_date'] || $year_end['enrollment_context']['movement_reason'])
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-sm font-semibold text-slate-900">Learner movement context</p>
                        @if ($year_end['enrollment_context']['effective_date'])
                            <p class="mt-2 text-sm text-slate-700">Effective date: {{ $year_end['enrollment_context']['effective_date'] }}</p>
                        @endif
                        @if ($year_end['enrollment_context']['movement_reason'])
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $year_end['enrollment_context']['movement_reason'] }}</p>
                        @endif
                    </div>
                @endif
            </x-section-panel>

            <x-section-panel title="SF10 workflow state" description="Draft export and finalization remain separate so the registrar repository only receives finalized official records.">
                <div class="flex flex-wrap gap-2">
                    <x-status-chip :tone="$preview_ready ? 'emerald' : 'amber'">
                        {{ $preview_ready ? 'Ready for draft export' : 'Blocked' }}
                    </x-status-chip>
                    <x-status-chip :tone="$finalization_status['tone']">
                        {{ $finalization_status['label'] }}
                    </x-status-chip>
                    <x-status-chip :tone="$year_end['final_quarter_ready'] ? 'emerald' : 'amber'">
                        Final quarter {{ $year_end['final_quarter_ready'] ? 'ready' : 'blocked' }}
                    </x-status-chip>
                    <x-status-chip :tone="$year_end['full_year_ready'] ? 'emerald' : 'amber'">
                        Full year {{ $year_end['full_year_ready'] ? 'ready' : 'blocked' }}
                    </x-status-chip>
                </div>

                <p class="mt-4 text-sm leading-6 text-slate-600">{{ $finalization_status['description'] }}</p>

                @if ($blockers !== [])
                    <div class="mt-4">
                        <x-blocker-panel title="Blocker reasons">
                            <ul class="mt-3 space-y-2 text-sm text-amber-800">
                                @foreach ($blockers as $blocker)
                                    <li>{{ $blocker }}</li>
                                @endforeach
                            </ul>
                        </x-blocker-panel>
                    </div>
                @endif

                @if ($finalization_blockers !== [])
                    <div class="mt-4">
                        <x-blocker-panel tone="rose" title="Finalization blockers">
                            <ul class="mt-3 space-y-2 text-sm text-rose-800">
                                @foreach ($finalization_blockers as $blocker)
                                    <li>{{ $blocker }}</li>
                                @endforeach
                            </ul>
                        </x-blocker-panel>
                    </div>
                @endif

                @if ($draft_review !== null)
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-sm font-semibold text-slate-900">Draft Review Context</p>
                        <p class="mt-2 text-sm text-slate-700">
                            Version {{ $draft_review['record_version'] }} · {{ $draft_review['template_name'] }} · v{{ $draft_review['template_version'] }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            Generated {{ $draft_review['generated_at'] ?? 'Unknown time' }} by {{ $draft_review['generated_by'] }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <x-status-chip :tone="$draft_review['is_finalized'] ? 'emerald' : 'amber'">
                                {{ $draft_review['is_finalized'] ? 'Finalized' : 'Draft' }}
                            </x-status-chip>
                            @if ($draft_review['finalized_at'] !== null)
                                <x-status-chip tone="slate">
                                    Finalized {{ $draft_review['finalized_at'] }}
                                </x-status-chip>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="mt-4 space-y-3">
                    @if ($export_ready)
                        <form method="POST" action="{{ route('adviser.sections.sf10.export', ['section' => $section, 'section_roster' => $sectionRoster]) }}">
                            @csrf
                            <button type="submit" class="ui-button ui-button--primary w-full">
                                Export SF10 draft
                            </button>
                        </form>
                    @endif

                    @if ($draft_review !== null && $finalize_ready)
                        <form method="POST" action="{{ route('adviser.sections.sf10.finalize', ['section' => $section, 'section_roster' => $sectionRoster, 'report_card_record' => $draft_review['id']]) }}">
                            @csrf
                            <button type="submit" class="ui-button ui-button--primary w-full">
                                Finalize SF10 for registrar handoff
                            </button>
                        </form>
                    @endif

                    <p class="text-xs leading-5 text-slate-500">
                        Export creates a draft version. Finalization is a separate explicit step and only finalized SF10 versions appear in the registrar repository.
                    </p>
                </div>
            </x-section-panel>
        </section>

        <x-table-wrapper title="Approved Year-End Subject Data" description="Only approved full-year subject data is included below. Missing or non-approved quarter data keeps the subject out of the SF10 draft path.">
            <div class="mt-6 overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Final Rating</th>
                            <th>Action Taken</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($year_end['subject_rows'] as $row)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $row['subject_name'] }}</p>
                                    <p class="table-support">{{ $row['subject_code'] }}</p>
                                </td>
                                <td class="text-slate-700">{{ $row['final_rating'] ?? 'Pending' }}</td>
                                <td class="text-slate-700">{{ $row['action_taken'] ?? 'Pending status' }}</td>
                                <td>
                                    <x-status-chip :tone="$row['status']['tone']">
                                        {{ $row['status']['label'] }}
                                    </x-status-chip>
                                    <p class="table-note">{{ $row['blockers'][0] ?? 'Included in the approved year-end data set.' }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-empty-state title="No year-end subject rows are available yet." description="The approved year-end data path for this learner is still incomplete or has not been generated." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-table-wrapper>

        <x-table-wrapper title="SF10 Export History" description="Each version is stored separately. Generate a new version whenever approved year-end data changes.">
            <div class="mt-6 overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Status</th>
                            <th>Template</th>
                            <th>Generated</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $record)
                            <tr>
                                <td class="font-semibold text-slate-900">Version {{ $record['record_version'] }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        <x-status-chip :state="$record['is_finalized'] ? 'finalized' : 'draft'">
                                            {{ $record['is_finalized'] ? 'Finalized' : 'Draft' }}
                                        </x-status-chip>
                                        @if ($record['finalized_at'] !== null)
                                            <x-status-chip tone="slate">
                                                {{ $record['finalized_at'] }}
                                            </x-status-chip>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-slate-700">{{ $record['template_name'] }} · v{{ $record['template_version'] }}</td>
                                <td class="text-slate-700">{{ $record['generated_at'] }} by {{ $record['generated_by'] }}</td>
                                <td class="text-right">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('adviser.sections.sf10.download', ['section' => $section, 'section_roster' => $sectionRoster, 'report_card_record' => $record['id']])"
                                            icon="download"
                                            title="Download SF10 export"
                                            aria-label="Download SF10 export"
                                        >
                                            Download
                                        </x-table-action-button>
                                        @if (! $record['is_finalized'] && $draft_review !== null && $draft_review['id'] === $record['id'] && $finalize_ready)
                                            <form method="POST" action="{{ route('adviser.sections.sf10.finalize', ['section' => $section, 'section_roster' => $sectionRoster, 'report_card_record' => $record['id']]) }}" class="table-row-actions__form">
                                                @csrf
                                                <x-table-action-button
                                                    type="submit"
                                                    tone="primary"
                                                    icon="check-circle"
                                                    title="Finalize SF10 export"
                                                    aria-label="Finalize SF10 export"
                                                >
                                                    Finalize
                                                </x-table-action-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state title="No SF10 draft exports have been generated yet." description="Create the first SF10 draft export to start the permanent-record history for this learner." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-table-wrapper>
    </div>
</x-app-layout>
