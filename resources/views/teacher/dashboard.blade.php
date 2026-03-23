<x-app-layout>
    <div class="studio-dashboard">
        <x-dashboard.hero
            eyebrow="Teacher workspace"
            title="Teacher Dashboard"
            description="Review your assigned loads, official learner lists, and returned submissions that need correction."
        >
            <x-slot name="meta">
                <x-status-chip tone="sky">{{ $summary['active_loads'] }} loads</x-status-chip>
                <x-status-chip tone="teal">{{ $summary['official_learners'] }} learners</x-status-chip>
                <x-status-chip :tone="$summary['returned_submissions'] > 0 ? 'amber' : 'emerald'">
                    {{ $summary['returned_submissions'] }} returns
                </x-status-chip>
            </x-slot>

            <x-slot name="actions">
                <a href="{{ route('teacher.loads.index') }}" class="ui-link-button">
                    Loads
                </a>
                <a href="{{ route('teacher.returned-submissions.index') }}" class="ui-button ui-button--primary">
                    Returns
                </a>
            </x-slot>
        </x-dashboard.hero>

        @include('teacher.partials.navigation')

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
                eyebrow="Correction queue"
                title="Recent Returned Submissions"
                description="Adviser remarks stay visible here so you can review what needs to be corrected next."
            >
                @if ($recentReturnedSubmissions->isNotEmpty())
                    <div class="studio-list">
                        @foreach ($recentReturnedSubmissions as $submission)
                            <a
                                href="{{ route('teacher.grade-entry.show', ['teacher_load' => $submission->teacherLoad, 'grading_period' => $submission->gradingPeriod]) }}"
                                class="studio-list__item"
                            >
                                <div class="studio-list__body">
                                    <div class="studio-list__header">
                                        <p class="studio-list__title">{{ $submission->teacherLoad->subject->name }}</p>
                                        <x-status-chip :tone="$submission->status->tone()">
                                            {{ $submission->status->label() }}
                                        </x-status-chip>
                                    </div>
                                    <p class="studio-list__meta">
                                        {{ $submission->teacherLoad->schoolYear->name }} ·
                                        {{ $submission->teacherLoad->section->gradeLevel->name }} ·
                                        {{ $submission->teacherLoad->section->name }} ·
                                        {{ $submission->gradingPeriod->quarter->label() }}
                                    </p>
                                    <p class="studio-list__description">
                                        {{ $submission->adviser_remarks ?: 'No adviser remarks recorded.' }}
                                    </p>
                                </div>
                                <span class="studio-list__arrow" aria-hidden="true">↗</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <x-empty-state
                        icon="undo"
                        title="No returned submissions are waiting for correction right now."
                        description="When advisers return a submission, the remarks and grade-entry shortcut will appear here."
                    />
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel
                eyebrow="Quick actions"
                title="Stay In The Grading Flow"
                description="Use the direct links below to move between your active teaching work areas."
            >
                <div class="studio-link-grid">
                    <a href="{{ route('teacher.loads.index') }}" class="studio-link-card studio-link-card--compact">
                        <span class="studio-link-card__icon">
                            <x-icon name="book" class="h-5 w-5" />
                        </span>
                        <span class="studio-link-card__title">My teaching loads</span>
                    </a>

                    <a href="{{ route('teacher.returned-submissions.index') }}" class="studio-link-card studio-link-card--compact">
                        <span class="studio-link-card__icon">
                            <x-icon name="undo" class="h-5 w-5" />
                        </span>
                        <span class="studio-link-card__title">Returned submissions</span>
                    </a>
                </div>
            </x-dashboard.panel>
        </section>
    </div>
</x-app-layout>
