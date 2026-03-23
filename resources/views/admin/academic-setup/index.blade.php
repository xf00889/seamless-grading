<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Admin tools</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Academic Setup</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Manage the academic structure used throughout the grading workflow foundation. This module keeps setup work limited to school years, grading periods, grade levels, sections, and subjects.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.academic-setup.partials.navigation')

        <section class="grid gap-4 xl:grid-cols-5">
            @foreach ($resourceCards as $card)
                <a href="{{ route($card['route']) }}" class="content-card block transition hover:-translate-y-0.5 hover:border-slate-300">
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-4 text-3xl font-semibold text-slate-900">{{ $card['count'] }}</p>
                    <p class="mt-2 text-sm font-medium text-slate-500">{{ $card['status'] }}</p>
                    <p class="mt-4 text-sm leading-6 text-slate-600">{{ $card['description'] }}</p>
                </a>
            @endforeach
        </section>

        <section class="content-card">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-lg font-semibold text-slate-900">Admin module scope</p>
                    <p class="mt-3 text-sm leading-6 text-slate-600">
                        This slice is intentionally focused on academic setup only. Teacher loads, import workflows, and grading actions remain outside this page until their own dedicated prompts.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    @can('create', \App\Models\SchoolYear::class)
                        <a href="{{ route('admin.academic-setup.school-years.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                            Create school year
                        </a>
                    @endcan

                    @can('create', \App\Models\GradingPeriod::class)
                        <a href="{{ route('admin.academic-setup.grading-periods.create') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                            Add grading period
                        </a>
                    @endcan
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
