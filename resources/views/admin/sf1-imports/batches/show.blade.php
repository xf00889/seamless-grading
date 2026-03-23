<x-app-layout>
    <x-slot name="header">
        <x-page-header
            eyebrow="Admin imports"
            :title="$importBatch->source_file_name"
            :description="$importBatch->section->schoolYear->name.' · '.$importBatch->section->gradeLevel->name.' · '.$importBatch->section->name"
        >
            <x-slot name="actions">
                <a href="{{ route('admin.sf1-imports.create') }}" class="ui-link-button">
                    Upload another batch
                </a>

                @if ($canConfirm)
                    @can('confirm', $importBatch)
                        <form method="POST" action="{{ route('admin.sf1-imports.confirm', $importBatch) }}" data-confirm-message="Confirm this SF1 import? Learners and rosters will be written after this review step.">
                            @csrf
                            <button type="submit" class="ui-button ui-button--primary">
                                Confirm import
                            </button>
                        </form>
                    @endcan
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="page-stack">
        @include('admin.academic-setup.partials.flash')
        @include('admin.sf1-imports.partials.navigation')

        <div class="stats-grid">
            <x-card>
                <p class="stat-card__label">Batch status</p>
                <div class="mt-4">
                    <x-status-chip :tone="$importBatch->status->tone()">
                        {{ $importBatch->status->label() }}
                    </x-status-chip>
                </div>
            </x-card>
            <x-stat-card label="Rows" :value="$importBatch->rows_count" icon="archive" />
            <x-stat-card label="Ready rows" :value="$importBatch->valid_rows" tone="success" icon="dashboard" />
            <x-stat-card label="Flagged rows" :value="$importBatch->invalid_rows" tone="warning" icon="monitor" />
        </div>

        <x-section-panel title="Batch metadata">
            <div class="meta-grid xl:grid-cols-4">
                <div class="meta-card">
                    <p class="meta-card__label">Section</p>
                    <p class="meta-card__value">{{ $importBatch->section->name }}</p>
                </div>
                <div class="meta-card">
                    <p class="meta-card__label">Adviser</p>
                    <p class="meta-card__value">{{ $importBatch->section->adviser?->name ?? 'No adviser assigned' }}</p>
                </div>
                <div class="meta-card">
                    <p class="meta-card__label">Uploaded by</p>
                    <p class="meta-card__value">{{ $importBatch->importedBy?->name ?? 'System' }}</p>
                </div>
                <div class="meta-card">
                    <p class="meta-card__label">Confirmed at</p>
                    <p class="meta-card__value">{{ $importBatch->confirmed_at?->format('M d, Y g:i A') ?? 'Pending review' }}</p>
                </div>
            </div>
        </x-section-panel>

        @if (! $canConfirm && $importBatch->status !== \App\Enums\ImportBatchStatus::Confirmed)
            <x-blocker-panel title="Preview review required">
                This batch cannot be confirmed yet. Resolve all validation, duplicate learner, duplicate LRN, and duplicate roster issues first.
            </x-blocker-panel>
        @endif

        <x-filter-bar title="Review import rows" description="Search by learner or row details and narrow the preview to only the row statuses you still need to resolve.">
            <form method="GET" action="{{ route('admin.sf1-imports.show', $importBatch) }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_auto]">
                <div>
                    <x-input-label for="search" value="Search rows" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Search by row number, LRN, or learner name" />
                </div>

                <div>
                    <x-input-label for="row_status" value="Row status" />
                    <select id="row_status" name="row_status" class="ui-select mt-1">
                        <option value="">All rows</option>
                        @foreach ($rowStatusOptions as $rowStatusOption)
                            <option value="{{ $rowStatusOption->value }}" @selected($filters['rowStatus'] === $rowStatusOption->value)>
                                {{ $rowStatusOption->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="action-bar items-end">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('admin.sf1-imports.show', $importBatch) }}" class="ui-link-button">
                        Reset
                    </a>
                </div>
            </form>
        </x-filter-bar>

        <x-table-wrapper title="Import preview" description="Each row shows its normalized learner data, current resolution status, and any validation or duplicate blockers still preventing confirmation." :count="$rows->total().' row'.($rows->total() === 1 ? '' : 's')">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Row</th>
                            <th>Learner</th>
                            <th>Status</th>
                            <th>Issues</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">Row {{ $row->row_number }}</p>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900">
                                        {{ $row->normalized_data['last_name'] ?? 'Missing last name' }},
                                        {{ $row->normalized_data['first_name'] ?? 'Missing first name' }}
                                    </p>
                                    <p class="table-support">
                                        LRN: {{ $row->normalized_data['lrn'] ?? 'Missing' }} ·
                                        Sex: {{ ucfirst($row->normalized_data['sex'] ?? 'missing') }} ·
                                        Birth date: {{ $row->normalized_data['birth_date'] ?? 'Missing' }}
                                    </p>
                                </td>
                                <td>
                                    <x-status-chip :tone="$row->status->tone()">
                                        {{ $row->status->label() }}
                                    </x-status-chip>
                                </td>
                                <td>
                                    @if (($row->errors ?? []) !== [])
                                        <ul class="space-y-2 text-sm text-rose-700">
                                            @foreach ($row->errors as $error)
                                                <li>{{ $error['message'] }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="text-slate-500">
                                            @if (($row->normalized_data['matched_roster_id'] ?? null) !== null && ($row->normalized_data['matched_roster_section_name'] ?? null) === $importBatch->section->name)
                                                Existing roster will be updated for this section.
                                            @else
                                                No blocking issues.
                                            @endif
                                        </p>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="table-row-actions ml-auto w-fit">
                                        @if ($importBatch->status !== \App\Enums\ImportBatchStatus::Confirmed)
                                            <x-table-action-button
                                                :href="route('admin.sf1-imports.rows.edit', [$importBatch, $row])"
                                                icon="edit"
                                                title="Resolve import row"
                                                aria-label="Resolve import row"
                                            >
                                                Resolve
                                            </x-table-action-button>
                                        @else
                                            <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Locked</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                    No import rows matched the current filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-slot name="footer">
                {{ $rows->links() }}
            </x-slot>
        </x-table-wrapper>
    </div>
</x-app-layout>
