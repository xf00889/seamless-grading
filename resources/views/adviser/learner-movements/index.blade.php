<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Adviser learner movement</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Transfer-Out and Dropout Exceptions</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Manage in-year learner movement exceptions for {{ $section->gradeLevel->name }} · {{ $section->name }} using official roster records, validated effective dates, and explicit audited transitions.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('adviser.sections.index', ['school_year_id' => $section->school_year_id]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Back to sections
                </a>
                <a href="{{ route('adviser.sections.year-end.index', ['section' => $section]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Year-end statuses
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @if ($errors->has('status') || $errors->has('effective_date') || $errors->has('reason') || $errors->has('record'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ $errors->first('status') ?: $errors->first('effective_date') ?: $errors->first('reason') ?: $errors->first('record') }}
            </div>
        @endif
        @include('adviser.partials.navigation')

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Official Learners</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $totals['official_learners'] }}</p>
                <p class="mt-2 text-sm text-slate-600">Official roster entries in this advisory section and school year.</p>
            </div>
            <div class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Active</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $totals['active'] }}</p>
                <p class="mt-2 text-sm text-slate-600">Learners currently eligible for normal grading unless another workflow blocks them.</p>
            </div>
            <div class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Transferred Out</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $totals['transferred_out'] }}</p>
                <p class="mt-2 text-sm text-slate-600">Transfer exceptions enforced from the stored effective date onward.</p>
            </div>
            <div class="content-card">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Dropped</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $totals['dropped'] }}</p>
                <p class="mt-2 text-sm text-slate-600">Dropout exceptions removed from later grading and finalization requirements.</p>
            </div>
        </section>

        <section class="content-card">
            <form method="GET" action="{{ route('adviser.sections.learner-movements.index', ['section' => $section]) }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_260px_auto]">
                <div>
                    <x-input-label for="search" value="Search learners" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Learner name or LRN" />
                </div>

                <div>
                    <x-input-label for="status" value="Roster status" />
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
                    <a href="{{ route('adviser.sections.learner-movements.index', ['section' => $section]) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
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
                            <th class="px-4 py-3">Current context</th>
                            <th class="px-4 py-3">Eligibility impact</th>
                            <th class="px-4 py-3">Update exception</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white align-top text-sm text-slate-700">
                        @forelse ($sectionRosters as $row)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $row['learner']['name'] }}</p>
                                    <p class="mt-1 text-slate-500">LRN: {{ $row['learner']['lrn'] }}</p>
                                    @if ($row['year_end_status'] !== null)
                                        <div class="mt-2">
                                            <x-status-chip :tone="$row['year_end_status']['tone']">
                                                Year-end: {{ $row['year_end_status']['label'] }}
                                            </x-status-chip>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <x-status-chip :tone="$row['movement_status']['tone']">
                                            {{ $row['movement_status']['label'] }}
                                        </x-status-chip>
                                    </div>
                                    <p class="mt-2 text-slate-600">
                                        Effective: {{ $row['effective_date_label'] ?? 'Not set' }}
                                    </p>
                                    <p class="mt-1 text-slate-500">
                                        {{ $row['movement_reason'] ?? 'No movement remarks recorded.' }}
                                    </p>
                                    @if ($row['movement_recorded_at'])
                                        <p class="mt-2 text-xs leading-5 text-slate-500">
                                            Recorded {{ $row['movement_recorded_at'] }}
                                            @if ($row['movement_recorded_by'])
                                                by {{ $row['movement_recorded_by'] }}
                                            @endif
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <p class="text-slate-700">{{ $row['eligibility_note'] }}</p>
                                    <p class="mt-2 text-xs leading-5 text-slate-500">
                                        Eligible periods: {{ $row['eligible_periods'] !== [] ? implode(', ', $row['eligible_periods']) : 'None' }}
                                    </p>
                                    <p class="mt-1 text-xs leading-5 text-slate-500">
                                        Blocked periods: {{ $row['blocked_periods'] !== [] ? implode(', ', $row['blocked_periods']) : 'None' }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <form method="POST" action="{{ route('adviser.sections.learner-movements.update', ['section' => $section, 'section_roster' => $row['section_roster_id']]) }}" class="space-y-3">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="search_filter" value="{{ $filters['search'] }}" />
                                        <input type="hidden" name="status_filter" value="{{ $filters['status'] }}" />
                                        <label class="block">
                                            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Roster status</span>
                                            <select name="status" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                                                <option value="active" @selected($row['form']['status'] === 'active')>Clear exception / Active</option>
                                                <option value="transferred_out" @selected($row['form']['status'] === 'transferred_out')>Transferred out</option>
                                                <option value="dropped" @selected($row['form']['status'] === 'dropped')>Dropped</option>
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Effective date</span>
                                            <input type="date" name="effective_date" value="{{ $row['form']['effective_date'] }}" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" />
                                            <span class="mt-2 block text-xs leading-5 text-slate-500">Required for transfer-out and used for dropout timing when supplied.</span>
                                        </label>
                                        <label class="block">
                                            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Reason / remarks</span>
                                            <textarea name="reason" rows="3" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" placeholder="Required for dropped learners and recommended for transfer-out corrections.">{{ $row['form']['reason'] }}</textarea>
                                        </label>
                                        <button type="submit" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-slate-800">
                                            Save exception
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">
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
