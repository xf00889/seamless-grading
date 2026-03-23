<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Adviser consolidation</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">By Subject</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Approved subject submissions for {{ $section->gradeLevel->name }} · {{ $section->name }} in {{ $gradingPeriod->quarter->label() }}.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('adviser.sections.consolidation.learners', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Switch to by learner
                </a>
                <a href="{{ route('adviser.sections.tracker', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Back to tracker
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('adviser.partials.navigation')
        @include('adviser.partials.readiness-panel', ['summary' => $summary])

        <section class="content-card">
            <form method="GET" action="{{ route('adviser.sections.consolidation.subjects', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto]">
                <div>
                    <x-input-label for="search" value="Search subjects" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Subject, code, or teacher name" />
                </div>

                <div class="flex items-end gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('adviser.sections.consolidation.subjects', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        @forelse ($submissions as $submission)
            <section class="content-card overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="text-lg font-semibold text-slate-900">{{ $submission['subject_name'] }}</p>
                            <x-status-chip tone="emerald">Approved</x-status-chip>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $submission['subject_code'] }} · {{ $submission['teacher_name'] }} · Approved {{ $submission['approved_at'] ?? 'recently' }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-4 py-3">Learner</th>
                                <th class="px-4 py-3">Enrollment</th>
                                <th class="px-4 py-3">Grade</th>
                                <th class="px-4 py-3">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                            @foreach ($submission['learners'] as $learner)
                                <tr>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-slate-900">{{ $learner['learner_name'] }}</p>
                                        <p class="mt-1 text-slate-500">{{ $learner['lrn'] }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-status-chip :tone="$learner['enrollment_status']['tone']">
                                            {{ $learner['enrollment_status']['label'] }}
                                        </x-status-chip>
                                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ $learner['remarks'] ?? 'Included in approved consolidation data.' }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-slate-900">{{ $learner['grade'] ?? '—' }}</td>
                                    <td class="px-4 py-4 text-slate-600">{{ $learner['grade'] !== null ? ($learner['remarks'] ?? 'No recorded remark.') : 'No official grade is used for this learner in the selected quarter.' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @empty
            <section class="content-card">
                <p class="text-sm text-slate-500">No approved subject submissions matched the current filters.</p>
            </section>
        @endforelse

        @if ($submissions->hasPages())
            <section class="content-card">
                {{ $submissions->links() }}
            </section>
        @endif
    </div>
</x-app-layout>
