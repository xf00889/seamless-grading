<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Academic setup</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Grading Periods</h1>
            </div>

            @can('create', \App\Models\GradingPeriod::class)
                <a href="{{ route('admin.academic-setup.grading-periods.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    New grading period
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.academic-setup.partials.navigation')

        <section class="content-card">
            <form method="GET" action="{{ route('admin.academic-setup.grading-periods.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_240px_180px_180px_auto]">
                <div>
                    <x-input-label for="search" value="Search school year" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Search by school year name" />
                </div>

                <div>
                    <x-input-label for="school_year_id" value="School year" />
                    <select id="school_year_id" name="school_year_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All school years</option>
                        @foreach ($schoolYears as $schoolYear)
                            <option value="{{ $schoolYear->id }}" @selected($filters['school_year_id'] === $schoolYear->id)>{{ $schoolYear->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="quarter" value="Quarter" />
                    <select id="quarter" name="quarter" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All quarters</option>
                        @foreach ($quarters as $quarter)
                            <option value="{{ $quarter->value }}" @selected($filters['quarter'] === $quarter->value)>{{ $quarter->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All statuses</option>
                        <option value="open" @selected($filters['status'] === 'open')>Open</option>
                        <option value="closed" @selected($filters['status'] === 'closed')>Closed</option>
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('admin.academic-setup.grading-periods.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
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
                            <th class="px-4 py-3">School year</th>
                            <th class="px-4 py-3">Quarter</th>
                            <th class="px-4 py-3">Date range</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Usage</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @forelse ($gradingPeriods as $gradingPeriod)
                            <tr>
                                <td class="px-4 py-4 font-semibold text-slate-900">{{ $gradingPeriod->schoolYear->name }}</td>
                                <td class="px-4 py-4">{{ $gradingPeriod->quarter->label() }}</td>
                                <td class="px-4 py-4 text-slate-500">
                                    @if ($gradingPeriod->starts_on && $gradingPeriod->ends_on)
                                        {{ $gradingPeriod->starts_on->format('M d, Y') }} to {{ $gradingPeriod->ends_on->format('M d, Y') }}
                                    @else
                                        Dates not set
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <x-status-chip :tone="$gradingPeriod->is_open ? 'sky' : 'slate'">
                                        {{ $gradingPeriod->is_open ? 'Open' : 'Closed' }}
                                    </x-status-chip>
                                </td>
                                <td class="px-4 py-4 text-slate-500">
                                    {{ $gradingPeriod->grade_submissions_count }} submissions
                                </td>
                                <td class="px-4 py-4">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('admin.academic-setup.grading-periods.show', $gradingPeriod)"
                                            icon="eye"
                                            title="View grading period"
                                            aria-label="View grading period"
                                        >
                                            View
                                        </x-table-action-button>
                                        @can('update', $gradingPeriod)
                                            <x-table-action-button
                                                :href="route('admin.academic-setup.grading-periods.edit', $gradingPeriod)"
                                                icon="edit"
                                                title="Edit grading period"
                                                aria-label="Edit grading period"
                                            >
                                                Edit
                                            </x-table-action-button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No grading periods matched the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-4">
                {{ $gradingPeriods->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
