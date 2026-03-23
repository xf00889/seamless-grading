<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Admin imports</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-900">SF1 Import Batches</h1>
            </div>

            @can('create', \App\Models\ImportBatch::class)
                <a href="{{ route('admin.sf1-imports.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Upload SF1 batch
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @include('admin.academic-setup.partials.flash')
        @include('admin.sf1-imports.partials.navigation')

        <section class="content-card">
            <form method="GET" action="{{ route('admin.sf1-imports.index') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_220px_240px_220px_auto]">
                <div>
                    <x-input-label for="search" value="Search" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Search by file name" />
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
                    <x-input-label for="section_id" value="Section" />
                    <select id="section_id" name="section_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected($filters['section_id'] === $section->id)>
                                {{ $section->name }} · {{ $section->schoolYear->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $statusOption)
                            <option value="{{ $statusOption->value }}" @selected($filters['status'] === $statusOption->value)>
                                {{ $statusOption->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('admin.sf1-imports.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
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
                            <th class="px-4 py-3">Batch</th>
                            <th class="px-4 py-3">Section</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Rows</th>
                            <th class="px-4 py-3">Timeline</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                        @forelse ($importBatches as $importBatch)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $importBatch->source_file_name }}</p>
                                    <p class="mt-1 text-slate-500">Uploaded by {{ $importBatch->importedBy?->name ?? 'System' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-900">{{ $importBatch->section->name }}</p>
                                    <p class="mt-1 text-slate-500">{{ $importBatch->section->gradeLevel->name }} · {{ $importBatch->section->schoolYear->name }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <x-status-chip :tone="$importBatch->status->tone()">
                                        {{ $importBatch->status->label() }}
                                    </x-status-chip>
                                </td>
                                <td class="px-4 py-4 text-slate-500">
                                    {{ $importBatch->rows_count }} total, {{ $importBatch->valid_rows }} ready, {{ $importBatch->invalid_rows }} flagged
                                </td>
                                <td class="px-4 py-4 text-slate-500">
                                    <p>{{ $importBatch->created_at->format('M d, Y g:i A') }}</p>
                                    <p class="mt-1">{{ $importBatch->confirmed_at?->format('M d, Y g:i A') ?? 'Not confirmed' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('admin.sf1-imports.show', $importBatch)"
                                            icon="eye"
                                            title="View SF1 import batch"
                                            aria-label="View SF1 import batch"
                                        >
                                            View
                                        </x-table-action-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No SF1 import batches matched the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-4">
                {{ $importBatches->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
