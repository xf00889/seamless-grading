<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Academic setup</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $gradingPeriod->quarter->label() }} · {{ $gradingPeriod->schoolYear->name }}</h1>
            </div>

            @can('update', $gradingPeriod)
                <a href="{{ route('admin.academic-setup.grading-periods.edit', $gradingPeriod) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                    Edit grading period
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.academic-setup.partials.navigation')

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="content-card space-y-4">
                <div class="flex items-center gap-3">
                    <x-status-chip :tone="$gradingPeriod->is_open ? 'sky' : 'slate'">
                        {{ $gradingPeriod->is_open ? 'Open' : 'Closed' }}
                    </x-status-chip>
                    <p class="text-sm text-slate-500">
                        @if ($gradingPeriod->starts_on && $gradingPeriod->ends_on)
                            {{ $gradingPeriod->starts_on->format('M d, Y') }} to {{ $gradingPeriod->ends_on->format('M d, Y') }}
                        @else
                            Dates not set
                        @endif
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Submissions</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $gradingPeriod->grade_submissions_count }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sheet exports</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $gradingPeriod->grading_sheet_exports_count }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Report cards</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $gradingPeriod->report_card_records_count }}</p>
                    </div>
                </div>
            </div>

            <aside class="space-y-4">
                @can('open', $gradingPeriod)
                    <section class="content-card space-y-3">
                        <p class="text-sm font-semibold text-slate-900">Status</p>
                        @if ($gradingPeriod->is_open)
                            <form method="POST" action="{{ route('admin.academic-setup.grading-periods.close', $gradingPeriod) }}" data-confirm-message="Close this grading period?">
                                @csrf
                                <x-secondary-button type="submit" class="w-full justify-center">Close grading period</x-secondary-button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.academic-setup.grading-periods.open', $gradingPeriod) }}" data-confirm-message="Open this grading period?">
                                @csrf
                                <x-primary-button class="w-full justify-center">Open grading period</x-primary-button>
                            </form>
                        @endif
                    </section>
                @endcan

                @can('delete', $gradingPeriod)
                    <section class="content-card space-y-3">
                        <p class="text-sm font-semibold text-slate-900">Danger zone</p>
                        <p class="text-sm leading-6 text-slate-500">
                            Deletion is only available while there are no linked submissions or exports.
                        </p>
                        <form method="POST" action="{{ route('admin.academic-setup.grading-periods.destroy', $gradingPeriod) }}" data-confirm-message="Delete this grading period? This action cannot be undone.">
                            @csrf
                            @method('DELETE')
                            <x-danger-button class="w-full justify-center">Delete grading period</x-danger-button>
                        </form>
                    </section>
                @endcan
            </aside>
        </section>
    </div>
</x-app-layout>
