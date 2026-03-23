<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Teacher workspace</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Grading Sheet Preview</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    {{ $load['subject_name'] }} · {{ $load['school_year_name'] }} · {{ $load['grade_level_name'] }} · {{ $load['section_name'] }} · {{ $grading_period['quarter_label'] }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('teacher.grade-entry.show', ['teacher_load' => $teacherLoad, 'grading_period' => $gradingPeriod]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Open grade entry
                </a>
                <a href="{{ route('teacher.loads.show', $teacherLoad) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Back to learner list
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('teacher.partials.navigation')

        <section class="grid gap-4 xl:grid-cols-4">
            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Teacher load</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">{{ $load['teacher_name'] }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $load['subject_code'] }} · {{ $load['subject_name'] }}</p>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Section</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">{{ $load['section_name'] }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $load['grade_level_name'] }} · {{ $load['school_year_name'] }}</p>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Grading period</p>
                <p class="mt-3 text-lg font-semibold text-slate-900">{{ $grading_period['quarter_label'] }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $grading_period['starts_on'] ?? 'Start date not set' }} to {{ $grading_period['ends_on'] ?? 'End date not set' }}</p>
            </article>

            <article class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Submission status</p>
                <div class="mt-3">
                    <x-status-chip :tone="$submission['status']['tone']">
                        {{ $submission['status']['label'] }}
                    </x-status-chip>
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    {{ $submission['submitted_at'] ?? 'No saved submission yet' }}
                </p>
            </article>
        </section>

        <section @class([
            'rounded-2xl px-6 py-5',
            'border border-emerald-200 bg-emerald-50' => $submission['workflow_notice']['tone'] === 'emerald',
            'border border-amber-200 bg-amber-50' => $submission['workflow_notice']['tone'] === 'amber',
            'border border-rose-200 bg-rose-50' => $submission['workflow_notice']['tone'] === 'rose',
            'border border-slate-200 bg-slate-50' => $submission['workflow_notice']['tone'] === 'slate',
        ])>
            <h2 @class([
                'text-lg font-semibold',
                'text-emerald-900' => $submission['workflow_notice']['tone'] === 'emerald',
                'text-amber-900' => $submission['workflow_notice']['tone'] === 'amber',
                'text-rose-900' => $submission['workflow_notice']['tone'] === 'rose',
                'text-slate-900' => $submission['workflow_notice']['tone'] === 'slate',
            ])>{{ $submission['workflow_notice']['title'] }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-700">{{ $submission['workflow_notice']['description'] }}</p>
            @if ($submission['adviser_remarks'])
                <p class="mt-3 text-sm leading-6 text-slate-700">Adviser remarks: {{ $submission['adviser_remarks'] }}</p>
            @endif
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <article class="content-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Template</h2>
                        <p class="mt-1 text-sm text-slate-500">Preview and export rely on the active validated grading-sheet template version.</p>
                    </div>
                    @if ($template)
                        <x-status-chip :tone="$template['mapping_status']['tone']">
                            {{ $template['mapping_status']['label'] }}
                        </x-status-chip>
                    @else
                        <x-status-chip tone="rose">Missing</x-status-chip>
                    @endif
                </div>

                @if ($template)
                    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Template name</dt>
                            <dd class="mt-2 text-sm text-slate-700">{{ $template['name'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Version</dt>
                            <dd class="mt-2 text-sm text-slate-700">v{{ $template['version'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Scope</dt>
                            <dd class="mt-2 text-sm text-slate-700">{{ $template['scope'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Required mappings</dt>
                            <dd class="mt-2 text-sm text-slate-700">
                                {{ $template['mapping_summary']['required_valid'] }} / {{ $template['mapping_summary']['required_total'] }} valid
                            </dd>
                        </div>
                    </dl>

                    @if ($template['is_file_missing'] || $template['mapping_summary']['issues'] !== [])
                        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4">
                            <p class="text-sm font-semibold text-amber-900">Template blockers</p>
                            <ul class="mt-3 space-y-2 text-sm text-amber-800">
                                @if ($template['is_file_missing'])
                                    <li>The active template file is missing from storage.</li>
                                @endif
                                @foreach ($template['mapping_summary']['issues'] as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @else
                    <p class="mt-6 text-sm text-slate-500">No active grading-sheet template is available for this load.</p>
                @endif
            </article>

            <aside class="content-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Export readiness</h2>
                        <p class="mt-1 text-sm text-slate-500">Export uses only persisted grade-submission and quarterly-grade data.</p>
                    </div>
                    <x-status-chip :tone="$export_ready ? 'emerald' : 'amber'">
                        {{ $export_ready ? 'Ready' : 'Blocked' }}
                    </x-status-chip>
                </div>

                @if ($blockers !== [])
                    <ul class="mt-6 space-y-3 text-sm text-slate-700">
                        @foreach ($blockers as $blocker)
                            <li class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">{{ $blocker }}</li>
                        @endforeach
                    </ul>
                @else
                    <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm text-emerald-800">
                        The active template, persisted submission, and official roster data are consistent for export.
                    </div>
                @endif

                <dl class="mt-6 space-y-4 text-sm text-slate-700">
                    <div>
                        <dt class="font-semibold uppercase tracking-[0.18em] text-slate-500">Adviser</dt>
                        <dd class="mt-2">{{ $load['adviser_name'] }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold uppercase tracking-[0.18em] text-slate-500">Saved rows</dt>
                        <dd class="mt-2">{{ count($rows) }} learner row{{ count($rows) === 1 ? '' : 's' }}</dd>
                    </div>
                    <div>
                        <dt class="font-semibold uppercase tracking-[0.18em] text-slate-500">Prior exports</dt>
                        <dd class="mt-2">{{ count($history) }}</dd>
                    </div>
                </dl>

                @if ($export_ready)
                    <form method="POST" action="{{ route('teacher.grading-sheet.export', ['teacher_load' => $teacherLoad, 'grading_period' => $gradingPeriod]) }}" class="mt-6">
                        @csrf
                        <x-primary-button class="w-full justify-center">Export grading sheet</x-primary-button>
                    </form>
                @endif

                @if ($errors->has('record'))
                    <p class="mt-4 text-sm text-rose-700">{{ $errors->first('record') }}</p>
                @endif
            </aside>
        </section>

        @if ($preview_ready)
            <section class="content-card overflow-hidden">
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-4 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Persisted learner grade rows</h2>
                        <p class="mt-1 text-sm text-slate-500">Only official roster learners and saved quarterly grades from this load and grading period are included.</p>
                    </div>
                    <span class="text-sm text-slate-500">{{ count($rows) }} learner{{ count($rows) === 1 ? '' : 's' }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-4 py-3">Learner</th>
                                <th class="px-4 py-3">LRN</th>
                                <th class="px-4 py-3">Sex</th>
                                <th class="px-4 py-3">Grade</th>
                                <th class="px-4 py-3">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-4 font-semibold text-slate-900">{{ $row['learner_name'] }}</td>
                                    <td class="px-4 py-4 text-slate-500">{{ $row['lrn'] }}</td>
                                    <td class="px-4 py-4 text-slate-500">{{ $row['sex'] }}</td>
                                    <td class="px-4 py-4 text-slate-500">{{ $row['grade'] ?? 'Blank' }}</td>
                                    <td class="px-4 py-4 text-slate-500">{{ $row['remarks'] ?? 'No remarks' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="content-card overflow-hidden">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-4 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Export history</h2>
                    <p class="mt-1 text-sm text-slate-500">Each export creates a new versioned history record and does not replace earlier files.</p>
                </div>
                <span class="text-sm text-slate-500">{{ count($history) }} export{{ count($history) === 1 ? '' : 's' }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            <th class="px-4 py-3">Export version</th>
                            <th class="px-4 py-3">Template</th>
                            <th class="px-4 py-3">Generated</th>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @forelse ($history as $export)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">Version {{ $export['version'] }}</p>
                                    <p class="mt-1 text-slate-500">Template v{{ $export['template_version'] }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $export['template_name'] }}</p>
                                </td>
                                <td class="px-4 py-4 text-slate-500">
                                    <p>{{ $export['exported_at'] }}</p>
                                    <p class="mt-1">{{ $export['exported_by'] }}</p>
                                </td>
                                <td class="px-4 py-4 text-slate-500">{{ $export['file_name'] }}</td>
                                <td class="px-4 py-4">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('teacher.grading-sheet.download', ['teacher_load' => $teacherLoad, 'grading_period' => $gradingPeriod, 'grading_sheet_export' => $export['model']])"
                                            icon="download"
                                            title="Download grading sheet export"
                                            aria-label="Download grading sheet export"
                                        >
                                            Download
                                        </x-table-action-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No grading sheet exports have been generated for this load and grading period yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
