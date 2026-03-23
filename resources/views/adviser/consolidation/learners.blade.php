<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Adviser consolidation</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">By Learner</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Official quarterly consolidation for {{ $section->gradeLevel->name }} · {{ $section->name }} using approved subject submissions only.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('adviser.sections.consolidation.subjects', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Switch to by subject
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
            <form method="GET" action="{{ route('adviser.sections.consolidation.learners', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto]">
                <div>
                    <x-input-label for="search" value="Search learners" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Learner name or LRN" />
                </div>

                <div class="flex items-end gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('adviser.sections.consolidation.learners', ['section' => $section, 'grading_period' => $gradingPeriod]) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="content-card overflow-hidden">
            @if ($subjectColumns === [])
                <div class="px-4 py-10 text-center text-sm text-slate-500">
                    No approved subject submissions are available yet for this section and grading period.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-4 py-3">Learner</th>
                                <th class="px-4 py-3">Enrollment</th>
                                <th class="px-4 py-3 text-right">SF9</th>
                                @foreach ($subjectColumns as $column)
                                    <th class="px-4 py-3">
                                        <div>{{ $column['subject_name'] }}</div>
                                        <div class="mt-1 text-[10px] font-medium tracking-[0.12em] text-slate-400">{{ $column['teacher_name'] }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                            @forelse ($learners as $learner)
                                <tr>
                                    <td class="px-4 py-4">
                                        <p class="font-semibold text-slate-900">{{ $learner['learner_name'] }}</p>
                                        <p class="mt-1 text-slate-500">{{ $learner['lrn'] }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <x-status-chip :tone="$learner['enrollment_status']['tone']">
                                            {{ $learner['enrollment_status']['label'] }}
                                        </x-status-chip>
                                        <p class="mt-2 text-xs leading-5 text-slate-500">{{ $learner['eligibility_note'] }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex justify-end">
                                            <a href="{{ route('adviser.sections.sf9.show', ['section' => $section, 'grading_period' => $gradingPeriod, 'section_roster' => $learner['section_roster_id']]) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                                                Preview
                                            </a>
                                        </div>
                                    </td>
                                    @foreach ($subjectColumns as $column)
                                        <td class="px-4 py-4 text-slate-900">
                                            {{ $learner['grades'][$column['grade_submission_id']]['grade'] ?? '—' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + count($subjectColumns) }}" class="px-4 py-10 text-center text-sm text-slate-500">
                                        No official learners matched the current filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 px-4 py-4">
                    {{ $learners->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
