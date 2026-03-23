<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Teacher workspace</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Returned Submissions</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Review returned grading submissions, adviser remarks, and the load each correction belongs to.
                </p>
            </div>

            <a href="{{ route('teacher.loads.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                Back to my loads
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('teacher.partials.navigation')

        <section class="content-card">
            <form method="GET" action="{{ route('teacher.returned-submissions.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_260px_auto]">
                <div>
                    <x-input-label for="search" value="Search" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Subject, section, adviser, remarks, or school year" />
                </div>

                <div>
                    <x-input-label for="school_year_id" value="School year" />
                    <select id="school_year_id" name="school_year_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All school years</option>
                        @foreach ($schoolYears as $schoolYear)
                            <option value="{{ $schoolYear->id }}" @selected($filters['school_year_id'] === $schoolYear->id)>
                                {{ $schoolYear->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="grading_period_id" value="Grading period" />
                    <select id="grading_period_id" name="grading_period_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All grading periods</option>
                        @foreach ($gradingPeriods as $gradingPeriod)
                            <option value="{{ $gradingPeriod->id }}" @selected($filters['grading_period_id'] === $gradingPeriod->id)>
                                {{ $gradingPeriod->schoolYear->name }} · {{ $gradingPeriod->quarter->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('teacher.returned-submissions.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
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
                            <th class="px-4 py-3">Assignment</th>
                            <th class="px-4 py-3">Grading period</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Adviser remarks</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @forelse ($returnedSubmissions as $submission)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $submission->teacherLoad->subject->name }}</p>
                                    <p class="mt-1 text-slate-500">
                                        {{ $submission->teacherLoad->schoolYear->name }} · {{ $submission->teacherLoad->section->gradeLevel->name }} · {{ $submission->teacherLoad->section->name }}
                                    </p>
                                    <p class="mt-1 text-slate-500">
                                        Adviser: {{ $submission->teacherLoad->section->adviser?->name ?? 'No adviser assigned' }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-slate-500">
                                    <p>{{ $submission->gradingPeriod->quarter->label() }}</p>
                                    <p class="mt-1">{{ $submission->returned_at?->format('M d, Y g:i A') ?? 'Awaiting timestamp' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <x-status-chip :tone="$submission->status->tone()">
                                        {{ $submission->status->label() }}
                                    </x-status-chip>
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    {{ $submission->adviser_remarks ?: 'No adviser remarks recorded.' }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('teacher.grade-entry.show', ['teacher_load' => $submission->teacherLoad, 'grading_period' => $submission->gradingPeriod])"
                                            icon="edit"
                                            title="Open grade entry"
                                            aria-label="Open grade entry"
                                        >
                                            Grades
                                        </x-table-action-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No returned submissions matched the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-4">
                {{ $returnedSubmissions->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
