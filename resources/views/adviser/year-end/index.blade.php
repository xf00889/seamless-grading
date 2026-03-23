<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Adviser year-end</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Learner Status Management</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Set explicit year-end learner outcomes for {{ $section->gradeLevel->name }} · {{ $section->name }} using official rosters and approved full-year data only.
                </p>
            </div>

            <a href="{{ route('adviser.sections.index', ['school_year_id' => $section->school_year_id]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                Back to sections
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @if ($errors->has('status') || $errors->has('reason'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ $errors->first('status') ?: $errors->first('reason') }}
            </div>
        @endif
        @include('adviser.partials.navigation')

        <section class="content-card">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">In-year movement exceptions</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Use the dedicated transfer-out and dropout workflow to control effective-date grading eligibility before setting final year-end outcomes.
                    </p>
                </div>

                <a href="{{ route('adviser.sections.learner-movements.index', ['section' => $section]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Open movement exceptions
                </a>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Official Learners</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $totals['official_learners'] }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ $section->schoolYear->name }} official roster entries in this advisory section.</p>
            </div>
            <div class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Final Quarter Ready</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $totals['final_quarter_ready'] }}</p>
                <p class="mt-2 text-sm text-slate-600">Learners with approved persisted grades in the final grading period.</p>
            </div>
            <div class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Full-Year Ready</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $totals['full_year_ready'] }}</p>
                <p class="mt-2 text-sm text-slate-600">Learners with approved year-end subject data across all configured grading periods.</p>
            </div>
            <div class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Statuses Set</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $totals['status_set'] }}</p>
                <p class="mt-2 text-sm text-slate-600">
                    Promoted {{ $totals['promoted'] }}, retained {{ $totals['retained'] }}, transferred out {{ $totals['transferred_out'] }}, dropped {{ $totals['dropped'] }}.
                </p>
            </div>
        </section>

        <section class="content-card">
            <form method="GET" action="{{ route('adviser.sections.year-end.index', $section) }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_260px_auto]">
                <div>
                    <x-input-label for="search" value="Search learners" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Learner name or LRN" />
                </div>

                <div>
                    <x-input-label for="status" value="Year-end status" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        @foreach ($statusOptions as $option)
                            <option value="{{ $option['value'] }}" @selected($filters['status'] === $option['value'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('adviser.sections.year-end.index', $section) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
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
                            <th class="px-4 py-3">Learner</th>
                            <th class="px-4 py-3">Enrollment context</th>
                            <th class="px-4 py-3">Readiness</th>
                            <th class="px-4 py-3">Year-end status</th>
                            <th class="px-4 py-3">Update</th>
                            <th class="px-4 py-3 text-right">SF10</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white align-top text-sm text-slate-700">
                        @forelse ($sectionRosters as $row)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $row['learner']['name'] }}</p>
                                    <p class="mt-1 text-slate-500">LRN: {{ $row['learner']['lrn'] }}</p>
                                    <p class="mt-1 text-slate-500">{{ $row['grade_level_name'] }} · {{ $row['section_name'] }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <x-status-chip :tone="$row['enrollment_context']['roster_status']['tone']">
                                            Roster: {{ $row['enrollment_context']['roster_status']['label'] }}
                                        </x-status-chip>
                                        <x-status-chip :tone="$row['enrollment_context']['learner_status']['tone']">
                                            Learner: {{ $row['enrollment_context']['learner_status']['label'] }}
                                        </x-status-chip>
                                    </div>
                                    @if ($row['transfer_context'])
                                        <p class="mt-2 text-xs leading-5 text-amber-700">Transferred-out learners are blocked from the normal promotion or retention flow.</p>
                                    @endif
                                    @if ($row['enrollment_context']['effective_date'])
                                        <p class="mt-2 text-xs leading-5 text-slate-500">Effective date: {{ $row['enrollment_context']['effective_date'] }}</p>
                                    @endif
                                    @if ($row['enrollment_context']['movement_reason'])
                                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $row['enrollment_context']['movement_reason'] }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <x-status-chip :tone="$row['final_quarter_ready'] ? 'emerald' : 'amber'">
                                            Final quarter: {{ $row['final_quarter_ready'] ? 'Ready' : 'Blocked' }}
                                        </x-status-chip>
                                        <x-status-chip :tone="$row['full_year_ready'] ? 'emerald' : 'amber'">
                                            Full year: {{ $row['full_year_ready'] ? 'Ready' : 'Blocked' }}
                                        </x-status-chip>
                                    </div>
                                    <p class="mt-2 text-slate-600">
                                        {{ $row['full_year_ready_count'] }} of {{ $row['expected_subject_count'] }} subject(s) approved for year-end.
                                    </p>
                                    @if ($row['general_average'] !== null)
                                        <p class="mt-1 text-slate-500">General average: {{ $row['general_average'] }}</p>
                                    @endif
                                    <p class="mt-2 text-xs leading-5 text-slate-500">
                                        {{ $row['full_year_blockers'][0] ?? 'All required full-year approvals are complete.' }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($row['year_end_status'] !== null)
                                        <x-status-chip :tone="$row['year_end_status']['tone']">
                                            {{ $row['year_end_status']['label'] }}
                                        </x-status-chip>
                                        <p class="mt-2 text-slate-500">
                                            Set {{ $row['year_end_status_set_at'] ?? 'just now' }}
                                            @if ($row['year_end_status_set_by'])
                                                by {{ $row['year_end_status_set_by'] }}
                                            @endif
                                        </p>
                                        @if (filled($row['year_end_status_reason']))
                                            <p class="mt-2 text-xs leading-5 text-slate-500">{{ $row['year_end_status_reason'] }}</p>
                                        @endif
                                    @else
                                        <x-status-chip tone="slate">Not set</x-status-chip>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <form method="POST" action="{{ route('adviser.sections.year-end.update', ['section' => $section, 'section_roster' => $row['section_roster_id']]) }}" class="space-y-3">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="search_filter" value="{{ $filters['search'] }}" />
                                        <input type="hidden" name="status_filter" value="{{ $filters['status'] }}" />
                                        <label class="block">
                                            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</span>
                                            <select name="status" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                                                @foreach (\App\Enums\LearnerYearEndStatus::cases() as $yearEndStatus)
                                                    <option value="{{ $yearEndStatus->value }}" @selected(($row['year_end_status']['value'] ?? null) === $yearEndStatus->value)>
                                                        {{ $yearEndStatus->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Reason</span>
                                            <textarea name="reason" rows="3" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" placeholder="Required for transferred out or dropped.">{{ old('reason') }}</textarea>
                                        </label>
                                        <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-slate-800">
                                            Save status
                                        </button>
                                    </form>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <a href="{{ route('adviser.sections.sf10.show', ['section' => $section, 'section_roster' => $row['section_roster_id']]) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                                        SF10 preview
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No official learners matched the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-4">
                {{ $sectionRosters->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
