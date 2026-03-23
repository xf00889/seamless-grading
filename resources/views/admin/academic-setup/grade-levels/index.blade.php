<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Academic setup</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">Grade Levels</h1>
            </div>

            @can('create', \App\Models\GradeLevel::class)
                <a href="{{ route('admin.academic-setup.grade-levels.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    New grade level
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.academic-setup.partials.navigation')

        <section class="content-card">
            <form method="GET" action="{{ route('admin.academic-setup.grade-levels.index') }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]">
                <div>
                    <x-input-label for="search" value="Search" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Search by code or name" />
                </div>

                <div class="flex items-end gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('admin.academic-setup.grade-levels.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
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
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Sections</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @forelse ($gradeLevels as $gradeLevel)
                            <tr>
                                <td class="px-4 py-4 font-semibold text-slate-900">{{ $gradeLevel->sort_order }}</td>
                                <td class="px-4 py-4">{{ $gradeLevel->code }}</td>
                                <td class="px-4 py-4">{{ $gradeLevel->name }}</td>
                                <td class="px-4 py-4 text-slate-500">{{ $gradeLevel->sections_count }}</td>
                                <td class="px-4 py-4">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('admin.academic-setup.grade-levels.show', $gradeLevel)"
                                            icon="eye"
                                            title="View grade level"
                                            aria-label="View grade level"
                                        >
                                            View
                                        </x-table-action-button>
                                        @can('update', $gradeLevel)
                                            <x-table-action-button
                                                :href="route('admin.academic-setup.grade-levels.edit', $gradeLevel)"
                                                icon="edit"
                                                title="Edit grade level"
                                                aria-label="Edit grade level"
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
                                    No grade levels matched the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-4">
                {{ $gradeLevels->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
