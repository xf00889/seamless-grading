<x-app-layout>
    <div class="studio-dashboard">
        <x-dashboard.hero
            :eyebrow="$headline['eyebrow']"
            :title="$headline['title']"
            :description="$headline['description']"
        >
            <x-slot name="meta">
                <x-status-chip tone="teal">Finalized</x-status-chip>
                <x-status-chip tone="slate">Read-only</x-status-chip>
            </x-slot>

            <x-slot name="actions">
                <a href="{{ route('registrar.records.index') }}" class="ui-button ui-button--primary">
                    Repository
                </a>
            </x-slot>
        </x-dashboard.hero>

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
                eyebrow="Latest finalizations"
                title="Recent Official Records"
                description="Review the newest finalized records directly from the registrar dashboard."
            >
                @if ($latestRecords !== [])
                    <div class="studio-list">
                        @foreach ($latestRecords as $record)
                            <a href="{{ route('registrar.records.show', ['report_card_record' => $record['id']]) }}" class="studio-list__item">
                                <div class="studio-list__body">
                                    <div class="studio-list__header">
                                        <p class="studio-list__title">{{ $record['learner_name'] }}</p>
                                        <x-status-chip :tone="$record['document_type']['tone']">
                                            {{ $record['document_type']['label'] }}
                                        </x-status-chip>
                                    </div>
                                    <p class="studio-list__meta">{{ $record['context'] }}</p>
                                    <p class="studio-list__description">Finalized {{ $record['finalized_at'] }}</p>
                                </div>
                                <span class="studio-list__arrow" aria-hidden="true">↗</span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <x-empty-state
                        icon="archive"
                        title="No finalized official records are visible yet."
                        description="The registrar repository will populate after approved data has been generated and finalized into official records."
                    />
                @endif
            </x-dashboard.panel>

            <x-dashboard.panel
                eyebrow="Quick access"
                title="Repository Workflows"
                description="Stay inside the read-only registrar surface for verification and historical checks."
            >
                <div class="studio-link-grid">
                    <a href="{{ route('registrar.records.index') }}" class="studio-link-card studio-link-card--compact">
                        <span class="studio-link-card__icon">
                            <x-icon name="archive" class="h-5 w-5" />
                        </span>
                        <span class="studio-link-card__title">Final records repository</span>
                    </a>
                </div>
            </x-dashboard.panel>
        </section>
    </div>
</x-app-layout>
