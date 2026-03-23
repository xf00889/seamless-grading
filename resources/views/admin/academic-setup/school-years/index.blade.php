<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Academic setup</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">School Years</h1>
            </div>

            @can('create', \App\Models\SchoolYear::class)
                <a href="{{ route('admin.academic-setup.school-years.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    New school year
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.academic-setup.partials.navigation')

        <section class="content-card">
            <form method="GET" action="{{ route('admin.academic-setup.school-years.index') }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_auto]">
                <div>
                    <x-input-label for="search" value="Search" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Search by school year name" />
                </div>

                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All statuses</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('admin.academic-setup.school-years.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
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
                            <th class="px-4 py-3">Date range</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Linked setup</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @forelse ($schoolYears as $schoolYear)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $schoolYear->name }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    {{ $schoolYear->starts_on->format('M d, Y') }} to {{ $schoolYear->ends_on->format('M d, Y') }}
                                </td>
                                <td class="px-4 py-4">
                                    <x-status-chip :tone="$schoolYear->is_active ? 'emerald' : 'slate'">
                                        {{ $schoolYear->is_active ? 'Active' : 'Inactive' }}
                                    </x-status-chip>
                                </td>
                                <td class="px-4 py-4 text-slate-500">
                                    {{ $schoolYear->grading_periods_count }} grading periods, {{ $schoolYear->sections_count }} sections
                                </td>
                                <td class="px-4 py-4">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('admin.academic-setup.school-years.show', $schoolYear)"
                                            icon="eye"
                                            title="View school year"
                                            aria-label="View school year"
                                        >
                                            View
                                        </x-table-action-button>
                                        @can('update', $schoolYear)
                                            <x-table-action-button
                                                :href="route('admin.academic-setup.school-years.edit', $schoolYear)"
                                                icon="edit"
                                                title="Edit school year"
                                                aria-label="Edit school year"
                                            >
                                                Edit
                                            </x-table-action-button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No school years matched the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-4">
                {{ $schoolYears->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
