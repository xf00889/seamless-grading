<x-app-layout>
    <div class="studio-dashboard">
        <x-dashboard.hero
            eyebrow="Adviser workspace"
            title="Quarterly Review Dashboard"
            description="Monitor advisory-section progress, surface missing subject submissions early, and move only fully reviewed work toward quarter consolidation."
        >
            <x-slot name="meta">
                @if ($selectedGradingPeriod !== null)
                    <x-status-chip tone="sky">{{ $selectedGradingPeriod->quarter->label() }}</x-status-chip>
                @endif

                @if ($selectedSchoolYearId !== null)
                    @php
                        $selectedSchoolYear = collect($availableSchoolYears)->firstWhere('id', $selectedSchoolYearId);
                    @endphp

                    @if ($selectedSchoolYear)
                        <x-status-chip tone="slate">{{ $selectedSchoolYear->name }}</x-status-chip>
                    @endif
                @endif

                <x-status-chip :tone="$totals['ready_sections'] > 0 ? 'emerald' : 'amber'">
                    {{ $totals['ready_sections'] }} ready
                </x-status-chip>
            </x-slot>

            <x-slot name="actions">
                <a href="{{ route('adviser.sections.index', ['school_year_id' => $filters['school_year_id'], 'grading_period_id' => $filters['grading_period_id']]) }}" class="ui-link-button">
                    Sections
                </a>
            </x-slot>
        </x-dashboard.hero>

        @include('admin.academic-setup.partials.flash')
        @include('adviser.partials.navigation')

        <x-dashboard.panel
            eyebrow="Review filters"
            title="Scope The Quarter Review"
            description="Filter the adviser dashboard by school year, grading period, and section search terms."
        >
            <form method="GET" action="{{ route('adviser.dashboard') }}" class="studio-filter-grid">
                <div>
                    <label for="search" class="ui-label">Search sections</label>
                    <input
                        id="search"
                        name="search"
                        type="text"
                        value="{{ $filters['search'] }}"
                        placeholder="Section, grade level, or school year"
                        class="ui-input mt-2"
                    >
                </div>

                <div>
                    <label for="school_year_id" class="ui-label">School year</label>
                    <select id="school_year_id" name="school_year_id" class="ui-select mt-2">
                        <option value="">Select school year</option>
                        @foreach ($availableSchoolYears as $schoolYear)
                            <option value="{{ $schoolYear->id }}" @selected($filters['school_year_id'] === $schoolYear->id)>
                                {{ $schoolYear->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="grading_period_id" class="ui-label">Grading period</label>
                    <select id="grading_period_id" name="grading_period_id" class="ui-select mt-2">
                        <option value="">Select grading period</option>
                        @foreach ($availableGradingPeriods as $gradingPeriod)
                            <option value="{{ $gradingPeriod->id }}" @selected($filters['grading_period_id'] === $gradingPeriod->id)>
                                {{ $gradingPeriod->quarter->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="studio-filter-grid__actions">
                    <button type="submit" class="ui-button ui-button--primary">Filter</button>
                    <a href="{{ route('adviser.dashboard') }}" class="ui-link-button">
                        Reset
                    </a>
                </div>
            </form>
        </x-dashboard.panel>

        <section class="studio-dashboard__metrics">
            @foreach ($metrics as $metric)
                <x-dashboard.metric-card
                    :label="$metric['label']"
                    :value="$metric['value']"
                    :description="$metric['description']"
                    :icon="$metric['icon']"
                    :tone="$metric['tone']"
                    :action-label="$metric['action_label'] ?? null"
                    :action-href="$metric['action_href'] ?? null"
                />
            @endforeach
        </section>

        @if ($selectedGradingPeriod === null)
            <x-dashboard.panel
                :eyebrow="$focus['eyebrow']"
                :title="$focus['title']"
                :description="$focus['description']"
                tone="soft"
            >
                <div class="studio-note">
                    <p class="studio-note__copy">{{ $focus['meta'] }}</p>
                    <a href="{{ $focus['action_href'] }}" class="ui-button ui-button--primary">
                        {{ $focus['action_label'] }}
                    </a>
                </div>
            </x-dashboard.panel>
        @else
            <section class="studio-dashboard__feature-grid">
                <x-dashboard.panel
                    :eyebrow="$chart['eyebrow']"
                    :title="$chart['title']"
                    :description="$chart['description']"
                    class="studio-dashboard__feature-panel"
                >
                    <x-dashboard.bar-chart :items="$chart['items']" />
                </x-dashboard.panel>

                <x-dashboard.panel
                    :eyebrow="$focus['eyebrow']"
                    :title="$focus['title']"
                    :description="$focus['description']"
                    tone="soft"
                >
                    <div class="studio-note">
                        <p class="studio-note__copy">{{ $focus['meta'] }}</p>
                        <a href="{{ $focus['action_href'] }}" class="ui-button ui-button--primary">
                            {{ $focus['action_label'] }}
                        </a>
                    </div>
                </x-dashboard.panel>
            </section>

            <section class="studio-dashboard__split-grid">
                <x-dashboard.panel
                    eyebrow="Needs attention"
                    title="Section Review Queue"
                    description="Open the sections that still have missing, returned, or not-yet-approved subject submissions."
                >
                    @if ($attentionItems !== [])
                        <div class="studio-list">
                            @foreach ($attentionItems as $item)
                                <a href="{{ $item['route'] }}" class="studio-list__item">
                                    <div class="studio-list__body">
                                        <div class="studio-list__header">
                                            <p class="studio-list__title">{{ $item['title'] }}</p>
                                            <x-status-chip :tone="$item['badge_tone']">{{ $item['badge'] }}</x-status-chip>
                                        </div>
                                        <p class="studio-list__meta">{{ $item['meta'] }}</p>
                                        <p class="studio-list__description">{{ $item['description'] }}</p>
                                    </div>
                                    <span class="studio-list__arrow" aria-hidden="true">↗</span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <x-empty-state
                            icon="section"
                            title="All scoped sections are currently stable."
                            description="Missing, returned, and other review blockers will appear here when a section needs follow-through."
                        />
                    @endif
                </x-dashboard.panel>

                <x-dashboard.panel
                    eyebrow="Readiness"
                    title="Quarter Completion"
                    description="Only approved data should move into advisory consolidation and official export workflows."
                >
                    <div class="studio-note__grid">
                        <div class="studio-note__item">
                            <span class="studio-note__label">Approved submissions</span>
                            <span class="studio-note__value">{{ $totals['approved_submissions'] }}</span>
                        </div>
                        <div class="studio-note__item">
                            <span class="studio-note__label">Missing submissions</span>
                            <span class="studio-note__value">{{ $totals['missing_submissions'] }}</span>
                        </div>
                        <div class="studio-note__item">
                            <span class="studio-note__label">Returned submissions</span>
                            <span class="studio-note__value">{{ $totals['returned_submissions'] }}</span>
                        </div>
                        <div class="studio-note__item">
                            <span class="studio-note__label">Completion</span>
                            <span class="studio-note__value">{{ $totals['completion_percentage'] }}%</span>
                        </div>
                    </div>
                </x-dashboard.panel>
            </section>

            <x-dashboard.panel
                eyebrow="Section status"
                title="Advisory Section Snapshot"
                :description="'Review tracker readiness for '.$selectedGradingPeriod->quarter->label().' and open consolidation only from approved data.'"
            >
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Progress</th>
                                <th>Workflow</th>
                                <th>Blockers</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sections as $section)
                                <tr>
                                    <td>
                                        <p class="font-semibold text-slate-900">{{ $section['grade_level_name'] }} · {{ $section['section_name'] }}</p>
                                        <p class="table-support">{{ $section['school_year_name'] }}</p>
                                    </td>
                                    <td>
                                        <p class="font-semibold text-slate-900">{{ $section['completion_percentage'] }}%</p>
                                        <p class="table-support">
                                            {{ $section['approved_submission_count'] }} of {{ $section['expected_submission_count'] }} approved
                                        </p>
                                    </td>
                                    <td>
                                        <div class="ui-cluster">
                                            <x-status-chip :tone="$section['status']['tone']">
                                                {{ $section['status']['label'] }}
                                            </x-status-chip>
                                            <x-status-chip tone="rose">Missing: {{ $section['missing_submission_count'] }}</x-status-chip>
                                            <x-status-chip tone="amber">Returned: {{ $section['returned_submission_count'] }}</x-status-chip>
                                        </div>
                                    </td>
                                    <td>{{ $section['blockers'][0] ?? 'No blockers recorded.' }}</td>
                                    <td class="text-right">
                                        <div class="table-row-actions ml-auto w-fit">
                                            <x-table-action-button
                                                :href="route('adviser.sections.tracker', ['section' => $section['section_id'], 'grading_period' => $selectedGradingPeriod])"
                                                icon="monitor"
                                                title="Open section tracker"
                                                aria-label="Open section tracker"
                                            >
                                                Tracker
                                            </x-table-action-button>
                                            <x-table-action-button
                                                :href="route('adviser.sections.consolidation.learners', ['section' => $section['section_id'], 'grading_period' => $selectedGradingPeriod])"
                                                icon="users"
                                                title="Open learner consolidation"
                                                aria-label="Open learner consolidation"
                                            >
                                                Learners
                                            </x-table-action-button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <x-empty-state
                                            title="No advisory sections matched the current filters."
                                            description="Try widening the search or clearing one of the quarter filters."
                                        />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-dashboard.panel>
        @endif
    </div>
</x-app-layout>
