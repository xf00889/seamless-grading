<x-app-layout>
    <x-slot name="header">
        <x-page-header
            eyebrow="Registrar records"
            title="Final Records Repository"
            description="Search finalized official SF9 and SF10 records only. Draft, returned, unapproved, and unofficial records stay out of this repository."
        >
            <x-slot name="actions">
                <a href="{{ route('registrar.dashboard') }}" class="ui-link-button">
                    Back to dashboard
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="page-stack">
        <div class="stats-grid md:grid-cols-3 xl:grid-cols-3">
            <x-stat-card label="Finalized Records" :value="$totals['records']" description="Official finalized learner records matching the current filters." icon="archive" />
            <x-stat-card label="SF9" :value="$totals['sf9']" description="Quarterly finalized report-card records." tone="success" icon="dashboard" />
            <x-stat-card label="SF10" :value="$totals['sf10']" description="Year-end finalized permanent records." tone="success" icon="template" />
        </div>

        <x-filter-bar title="Repository filters" description="Find finalized records by learner identity, school context, document type, and finalization metadata.">
            <form method="GET" action="{{ route('registrar.records.index') }}" class="grid gap-4 xl:grid-cols-4">
                <div>
                    <x-input-label for="search" value="Learner name" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="Last name, first name, or keyword" />
                </div>

                <div>
                    <x-input-label for="lrn" value="LRN" />
                    <x-text-input id="lrn" name="lrn" type="text" class="mt-1 block w-full" :value="$filters['lrn']" placeholder="Learner reference number" />
                </div>

                <div>
                    <x-input-label for="school_year_id" value="School year" />
                    <select id="school_year_id" name="school_year_id" class="ui-select mt-1">
                        <option value="">All school years</option>
                        @foreach ($schoolYears as $schoolYear)
                            <option value="{{ $schoolYear->id }}" @selected($filters['school_year_id'] === $schoolYear->id)>
                                {{ $schoolYear->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="grade_level_id" value="Grade level" />
                    <select id="grade_level_id" name="grade_level_id" class="ui-select mt-1">
                        <option value="">All grade levels</option>
                        @foreach ($gradeLevels as $gradeLevel)
                            <option value="{{ $gradeLevel->id }}" @selected($filters['grade_level_id'] === $gradeLevel->id)>
                                {{ $gradeLevel->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="section_id" value="Section" />
                    <select id="section_id" name="section_id" class="ui-select mt-1">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected($filters['section_id'] === $section->id)>
                                {{ $section->name }} · {{ $section->gradeLevel?->name }} · {{ $section->schoolYear?->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="document_type" value="Document type" />
                    <select id="document_type" name="document_type" class="ui-select mt-1">
                        @foreach ($documentTypeOptions as $option)
                            <option value="{{ $option['value'] }}" @selected($filters['document_type'] === $option['value'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="finalization_status" value="Finalization status" />
                    <select id="finalization_status" name="finalization_status" class="ui-select mt-1">
                        @foreach ($finalizationStatusOptions as $option)
                            <option value="{{ $option['value'] }}" @selected($filters['finalization_status'] === $option['value'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="action-bar items-end xl:col-span-2">
                    <x-primary-button>Apply filters</x-primary-button>
                    <a href="{{ route('registrar.records.index') }}" class="ui-link-button">
                        Reset
                    </a>
                </div>
            </form>
        </x-filter-bar>

        <x-table-wrapper title="Official finalized records" description="Only finalized SF9 and SF10 versions appear in this repository." :count="$records->total().' official record'.($records->total() === 1 ? '' : 's')">
            <x-slot name="actions">
                <x-status-chip tone="teal">Finalized only</x-status-chip>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Learner</th>
                            <th>School context</th>
                            <th>Record</th>
                            <th>Version</th>
                            <th>Timestamps</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $record['learner_name'] }}</p>
                                    <p class="table-support">LRN: {{ $record['lrn'] }}</p>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $record['school_year_name'] }}</p>
                                    <p class="table-support">{{ $record['grade_level_name'] }} · {{ $record['section_name'] }}</p>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        <x-status-chip :tone="$record['document_type']['tone']">
                                            {{ $record['document_type']['label'] }}
                                        </x-status-chip>
                                        <x-status-chip :tone="$record['finalization_status']['tone']">
                                            {{ $record['finalization_status']['label'] }}
                                        </x-status-chip>
                                    </div>
                                    <p class="table-note">{{ $record['period_label'] }}</p>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900">Version {{ $record['record_version'] }}</p>
                                    <p class="table-support">Template v{{ $record['template_version'] }}</p>
                                </td>
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $record['finalized_at'] ?? 'Not finalized' }}</p>
                                    <p class="table-support">Finalized by {{ $record['finalized_by'] }}</p>
                                    <p class="table-note">Generated {{ $record['generated_at'] ?? 'Unknown' }}</p>
                                </td>
                                <td class="text-right">
                                    <div class="table-row-actions ml-auto w-fit">
                                        <x-table-action-button
                                            :href="route('registrar.records.show', ['report_card_record' => $record['id']])"
                                            icon="eye"
                                            title="Verify official record"
                                            aria-label="Verify official record"
                                        >
                                            Verify
                                        </x-table-action-button>
                                        <x-table-action-button
                                            :href="route('registrar.records.learners.show', ['learner' => $record['learner_id']])"
                                            icon="history"
                                            title="Open learner history"
                                            aria-label="Open learner history"
                                        >
                                            History
                                        </x-table-action-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty-state title="No finalized official records matched these filters." description="Try widening the learner search or clearing one of the school-context filters." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-slot name="footer">
                {{ $records->links() }}
            </x-slot>
        </x-table-wrapper>
    </div>
</x-app-layout>
