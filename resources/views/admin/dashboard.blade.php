<x-app-layout>
    <div class="studio-dashboard">
        <x-dashboard.hero
            :eyebrow="$headline['eyebrow']"
            :title="$headline['title']"
            :description="$headline['description']"
        >
            <x-slot name="meta">
                <x-status-chip tone="sky">{{ $readiness['quarter_label'] }}</x-status-chip>
                <x-status-chip tone="slate">Due {{ $readiness['deadline'] }}</x-status-chip>
                <x-status-chip tone="teal">
                    {{ $readiness['finalized_sf9_records'] }}/{{ $readiness['required_sf9_roster_records'] }} final
                </x-status-chip>
            </x-slot>

            <x-slot name="actions">
                <a href="{{ route('admin.submission-monitoring') }}" class="ui-link-button">
                    Monitoring
                </a>
                <a href="{{ route('admin.submission-monitoring.audit') }}" class="ui-button ui-button--primary">
                    Audit
                </a>
            </x-slot>
        </x-dashboard.hero>

        <section class="studio-dashboard__metrics">
            @foreach ($stats as $stat)
                <x-dashboard.metric-card
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :description="$stat['description']"
                    :icon="$stat['icon']"
                    :tone="$stat['tone']"
                    :status="$stat['status']"
                    :status-tone="$stat['status_tone']"
                    :action-label="$stat['action_label']"
                    :action-href="$stat['action_href']"
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
                <x-dashboard.bar-chart :items="$chart['items']" label="Admin workflow snapshot" />
            </x-dashboard.panel>

            <x-dashboard.panel
                :eyebrow="$spotlight['eyebrow']"
                :title="$spotlight['title']"
                :description="$spotlight['description']"
                tone="soft"
            >
                <div class="studio-note">
                    <div class="studio-note__grid">
                        <div class="studio-note__item">
                            <span class="studio-note__label">Completed sections</span>
                            <span class="studio-note__value">{{ $readiness['completed_sections'] }}</span>
                        </div>
                        <div class="studio-note__item">
                            <span class="studio-note__label">Late items</span>
                            <span class="studio-note__value">{{ $readiness['late_submissions'] }}</span>
                        </div>
                    </div>

                    <a href="{{ $spotlight['action_href'] }}" class="ui-button ui-button--primary">
                        {{ $spotlight['action_label'] }}
                    </a>
                </div>
            </x-dashboard.panel>
        </section>

        <section class="studio-dashboard__split-grid">
            <x-dashboard.panel title="Sex Distribution">
                <x-slot name="actions">
                    <x-status-chip tone="slate">
                        {{ number_format($demographics['official_roster_total']) }} official
                    </x-status-chip>
                </x-slot>

                <x-dashboard.apex-chart
                    :config="$demographics['sex_chart']"
                    label="Official roster sex distribution"
                />
            </x-dashboard.panel>

            <x-dashboard.panel title="Age Bands">
                <x-slot name="actions">
                    <x-status-chip tone="slate">
                        As of {{ $demographics['reference_date_label'] }}
                    </x-status-chip>
                </x-slot>

                <x-dashboard.apex-chart
                    :config="$demographics['age_chart']"
                    label="Official roster age-band distribution"
                />
            </x-dashboard.panel>
        </section>

        <section class="studio-dashboard__split-grid">
            <x-dashboard.panel title="Enrollment Status">
                <x-dashboard.apex-chart
                    :config="$demographics['enrollment_status_chart']"
                    label="Official roster enrollment status distribution"
                />
            </x-dashboard.panel>

            <x-dashboard.panel title="Learners By Grade Level">
                <x-dashboard.apex-chart
                    :config="$demographics['grade_level_chart']"
                    label="Official roster grade-level distribution"
                />
            </x-dashboard.panel>
        </section>

        <section class="studio-dashboard__split-grid">
            <x-dashboard.panel
                eyebrow="Needs attention"
                title="Blocked Or Time-Sensitive Work"
                description="Surface the sections and subject loads that still need human follow-through."
            >
                @if ($needsAttention !== [])
                    <div class="studio-list">
                        @foreach ($needsAttention as $item)
                            <a href="{{ $item['route'] }}" class="studio-list__item">
                                <div class="studio-list__body">
                                    <div class="studio-list__header">
                                        <p class="studio-list__title">{{ $item['title'] }}</p>
                                        <x-status-chip tone="amber">{{ $item['badge'] }}</x-status-chip>
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
                        icon="monitor"
                        title="No urgent blockers surfaced from monitoring."
                        description="The current snapshot does not show missing, returned, or late work on the preview set."
                    />
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel
                eyebrow="Readiness"
                title="Deadline And Completion Signals"
                description="Quarter locking and official record flow should move only after approved work and finalized learner records are complete."
            >
                <div class="studio-note__grid">
                    <div class="studio-note__item">
                        <span class="studio-note__label">Quarter</span>
                        <span class="studio-note__value">{{ $readiness['quarter_label'] }}</span>
                    </div>
                    <div class="studio-note__item">
                        <span class="studio-note__label">Deadline</span>
                        <span class="studio-note__value">{{ $readiness['deadline'] }}</span>
                    </div>
                    <div class="studio-note__item">
                        <span class="studio-note__label">Completed sections</span>
                        <span class="studio-note__value">{{ $readiness['completed_sections'] }}</span>
                    </div>
                    <div class="studio-note__item">
                        <span class="studio-note__label">Late submission items</span>
                        <span class="studio-note__value">{{ $readiness['late_submissions'] }}</span>
                    </div>
                </div>
            </x-dashboard.panel>
        </section>
    </div>
</x-app-layout>
