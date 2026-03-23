<x-app-layout>
    <x-slot name="header">
        <x-page-header
            eyebrow="Adviser SF9 workflow"
            title="SF9 Preview"
            description="Preview, export, and finalize the learner quarterly record using only approved official-roster grades and the active validated SF9 template."
        >
            <x-slot name="actions">
                <a href="{{ route('adviser.sections.consolidation.learners', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="ui-link-button">
                    Back to by learner
                </a>
                <a href="{{ route('adviser.sections.tracker', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="ui-link-button">
                    Back to tracker
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="page-stack">
        @include('admin.academic-setup.partials.flash')
        @include('adviser.partials.navigation')
        @include('adviser.partials.readiness-panel', ['summary' => $summary])

        <section class="grid gap-4 xl:grid-cols-3">
            <x-section-panel title="Learner record" class="xl:col-span-2">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ $learner['name'] }}</h2>
                        <p class="mt-2 text-sm text-slate-500">LRN {{ $learner['lrn'] }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-status-chip :tone="$learner['enrollment_status']['tone']">
                            {{ $learner['enrollment_status']['label'] }}
                        </x-status-chip>
                        <x-status-chip :tone="$finalization_status['tone']">
                            {{ $finalization_status['label'] }}
                        </x-status-chip>
                    </div>
                </div>

                <dl class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Section</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-900">{{ $section->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Grade level</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-900">{{ $section->gradeLevel->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">School year</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-900">{{ $section->schoolYear->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Grading period</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-900">{{ $gradingPeriod->quarter->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Adviser</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-900">{{ $section->adviser?->name ?? 'No adviser assigned' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">General average</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-900">{{ $general_average ?? 'Not available yet' }}</dd>
                    </div>
                </dl>

                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-sm font-semibold text-slate-900">Learner eligibility context</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $learner['eligibility_note'] }}</p>
                    @if ($learner['effective_date'])
                        <p class="mt-2 text-xs leading-5 text-slate-500">Effective date: {{ $learner['effective_date'] }}</p>
                    @endif
                </div>

                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Template and workflow state</p>
                            <p class="mt-2 text-sm text-slate-600">{{ $finalization_status['description'] }}</p>
                        </div>
                        @if ($template !== null)
                            <div class="text-sm text-slate-600">
                                <p><span class="font-semibold text-slate-900">Template:</span> {{ $template['name'] }}</p>
                                <p class="mt-1"><span class="font-semibold text-slate-900">Scope:</span> {{ $template['scope'] }}</p>
                                <p class="mt-1"><span class="font-semibold text-slate-900">Version:</span> {{ $template['version'] }}</p>
                                <p class="mt-1"><span class="font-semibold text-slate-900">Mapping status:</span> {{ $template['mapping_status']['label'] }}</p>
                            </div>
                        @else
                            <div class="text-sm font-semibold text-rose-700">
                                No active SF9 template
                            </div>
                        @endif
                    </div>
                </div>
            </x-section-panel>

            <x-section-panel title="Actions" description="Export creates the next versioned SF9 record, and finalization separately marks the latest version as official.">
                <div class="mt-4 space-y-3">
                    <form method="POST" action="{{ route('adviser.sections.sf9.export', ['section' => $section, 'grading_period' => $gradingPeriod, 'section_roster' => $sectionRoster]) }}">
                        @csrf
                        <x-primary-button class="w-full justify-center" :disabled="! $export_ready">
                            Export SF9
                        </x-primary-button>
                    </form>

                    <form method="POST" action="{{ route('adviser.sections.sf9.finalize', ['section' => $section, 'grading_period' => $gradingPeriod, 'section_roster' => $sectionRoster]) }}">
                        @csrf
                        <button type="submit" class="ui-button ui-button--primary w-full" @disabled(! $finalize_ready)>
                            Finalize Latest Version
                        </button>
                    </form>
                </div>

                @if ($blockers !== [])
                    <div class="mt-6">
                        <x-blocker-panel tone="rose" title="Preview and export blockers">
                            <ul class="mt-3 space-y-2 text-sm text-rose-900">
                                @foreach ($blockers as $blocker)
                                    <li>{{ $blocker }}</li>
                                @endforeach
                            </ul>
                        </x-blocker-panel>
                    </div>
                @endif

                @if ($finalization_blockers !== [])
                    <div class="mt-6">
                        <x-blocker-panel title="Finalization blockers">
                            <ul class="mt-3 space-y-2 text-sm text-amber-900">
                                @foreach ($finalization_blockers as $blocker)
                                    <li>{{ $blocker }}</li>
                                @endforeach
                            </ul>
                        </x-blocker-panel>
                    </div>
                @endif
            </x-section-panel>
        </section>

        <x-table-wrapper title="Subject source data" description="Only approved subject submissions with official roster grades feed the SF9 export.">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Submission</th>
                            <th>Grade</th>
                            <th>Remarks</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subject_requirements as $row)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $row['subject_name'] }}</p>
                                    <p class="table-support">{{ $row['subject_code'] }}</p>
                                </td>
                                <td>{{ $row['teacher_name'] }}</td>
                                <td>
                                    <div class="flex flex-col gap-2">
                                        <x-status-chip :tone="$row['submission_status']['tone']">
                                            {{ $row['submission_status']['label'] }}
                                        </x-status-chip>
                                        <span class="table-note mt-0">{{ $row['approved_at'] ?? 'Not approved yet' }}</span>
                                    </div>
                                </td>
                                <td class="font-semibold text-slate-900">{{ $row['grade'] ?? '—' }}</td>
                                <td class="text-slate-600">{{ $row['remarks'] ?? '—' }}</td>
                                <td class="text-slate-600">{{ $row['blocker'] ?? 'Included in approved export data.' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty-state title="No active subject assignments were found." description="This section does not currently have any active subject loads tied to the selected grading period." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-table-wrapper>

        <x-table-wrapper title="Export history" description="Each export creates a new versioned SF9 record. Finalization applies to the latest export version only.">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Template</th>
                            <th>Generated</th>
                            <th>Finalization</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $record)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">Version {{ $record['record_version'] }}</p>
                                    <p class="table-support">{{ $record['file_name'] }}</p>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $record['template_name'] }}</p>
                                    <p class="table-support">Template v{{ $record['template_version'] ?? '—' }}</p>
                                </td>
                                <td class="text-slate-600">
                                    <p>{{ $record['generated_at'] ?? 'Not recorded' }}</p>
                                    <p class="table-support mt-1">{{ $record['generated_by'] }}</p>
                                </td>
                                <td>
                                    <div class="flex flex-col gap-2">
                                        <x-status-chip :state="$record['is_finalized'] ? 'finalized' : 'draft'">
                                            {{ $record['is_finalized'] ? 'Finalized' : 'Not finalized' }}
                                        </x-status-chip>
                                        <span class="table-note mt-0">
                                            {{ $record['is_finalized'] ? (($record['finalized_at'] ?? 'Not recorded').' · '.$record['finalized_by']) : 'Awaiting adviser finalization' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('adviser.sections.sf9.download', ['section' => $section, 'grading_period' => $gradingPeriod, 'section_roster' => $sectionRoster, 'report_card_record' => $record['id']])"
                                            icon="download"
                                            title="Download SF9 export"
                                            aria-label="Download SF9 export"
                                        >
                                            Download
                                        </x-table-action-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state title="No SF9 exports have been generated yet." description="Export history will appear here once an adviser creates the first versioned SF9 record for this learner." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-table-wrapper>
    </div>
</x-app-layout>
