@props([
    'summary',
])

<x-section-panel title="Quarter readiness" description="Approved submissions are the only data included in consolidation. Missing, returned, draft, submitted, and locked subject submissions keep the section from being ready for finalization.">
    <x-slot name="actions">
        <x-status-chip :tone="$summary['status']['tone']">
            {{ $summary['status']['label'] }}
        </x-status-chip>
    </x-slot>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
        <div>
            <div class="stats-grid xl:grid-cols-2">
                <x-stat-card label="Expected" :value="$summary['expected_submission_count']" />
                <x-stat-card label="Approved" :value="$summary['approved_submission_count']" tone="success" />
                <x-stat-card label="Missing" :value="$summary['missing_submission_count']" tone="danger" />
                <x-stat-card label="Returned" :value="$summary['returned_submission_count']" tone="warning" />
            </div>
        </div>

        @if ($summary['blockers'] !== [])
            <x-alert-panel tone="amber" title="Current blockers">
                <ul class="space-y-2">
                    @foreach (array_slice($summary['blockers'], 0, 5) as $blocker)
                        <li>{{ $blocker }}</li>
                    @endforeach
                </ul>
            </x-alert-panel>
        @endif
    </div>
</x-section-panel>
