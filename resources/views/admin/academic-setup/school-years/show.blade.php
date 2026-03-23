<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Academic setup</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ $schoolYear->name }}</h1>
            </div>

            <div class="flex flex-wrap gap-3">
                @can('update', $schoolYear)
                    <a href="{{ route('admin.academic-setup.school-years.edit', $schoolYear) }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                        Edit school year
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.academic-setup.partials.navigation')

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="content-card space-y-4">
                <div class="flex items-center gap-3">
                    <x-status-chip :tone="$schoolYear->is_active ? 'emerald' : 'slate'">
                        {{ $schoolYear->is_active ? 'Active' : 'Inactive' }}
                    </x-status-chip>
                    <p class="text-sm text-slate-500">
                        {{ $schoolYear->starts_on->format('M d, Y') }} to {{ $schoolYear->ends_on->format('M d, Y') }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Grading periods</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $schoolYear->grading_periods_count }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sections</p>
                        <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $schoolYear->sections_count }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-sm font-semibold text-slate-900">Grading periods in this school year</p>
                    <div class="mt-3 space-y-3">
                        @forelse ($schoolYear->gradingPeriods as $gradingPeriod)
                            <a href="{{ route('admin.academic-setup.grading-periods.show', $gradingPeriod) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3 text-sm transition hover:border-slate-300">
                                <span class="font-semibold text-slate-900">{{ $gradingPeriod->quarter->label() }}</span>
                                <x-status-chip :tone="$gradingPeriod->is_open ? 'sky' : 'slate'">
                                    {{ $gradingPeriod->is_open ? 'Open' : 'Closed' }}
                                </x-status-chip>
                            </a>
                        @empty
                            <p class="text-sm text-slate-500">No grading periods have been added yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <aside class="space-y-4">
                <section class="content-card">
                    <p class="text-sm font-semibold text-slate-900">Quick actions</p>
                    <div class="mt-4 grid gap-3">
                        @can('create', \App\Models\GradingPeriod::class)
                            <a href="{{ route('admin.academic-setup.grading-periods.create', ['school_year_id' => $schoolYear->id]) }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                                Add grading period
                            </a>
                        @endcan

                        @can('create', \App\Models\Section::class)
                            <a href="{{ route('admin.academic-setup.sections.create', ['school_year_id' => $schoolYear->id]) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                                Add section
                            </a>
                        @endcan
                    </div>
                </section>

                @can('activate', $schoolYear)
                    <section class="content-card space-y-3">
                        <p class="text-sm font-semibold text-slate-900">Status</p>
                        @if ($schoolYear->is_active)
                            <form method="POST" action="{{ route('admin.academic-setup.school-years.deactivate', $schoolYear) }}" data-confirm-message="Deactivate this school year?">
                                @csrf
                                <x-secondary-button type="submit" class="w-full justify-center">Deactivate school year</x-secondary-button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.academic-setup.school-years.activate', $schoolYear) }}" data-confirm-message="Activate this school year and mark all others inactive?">
                                @csrf
                                <x-primary-button class="w-full justify-center">Activate school year</x-primary-button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.academic-setup.school-years.destroy', $schoolYear) }}" data-confirm-message="Delete this school year? This action cannot be undone.">
                            @csrf
                            @method('DELETE')
                            <x-danger-button class="w-full justify-center">Delete school year</x-danger-button>
                        </form>
                    </section>
                @endcan
            </aside>
        </section>
    </div>
</x-app-layout>
