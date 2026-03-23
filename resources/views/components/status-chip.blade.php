@props([
    'tone' => null,
    'state' => null,
])

@php
    $stateClasses = [
        'draft' => 'status-chip--draft',
        'submitted' => 'status-chip--submitted',
        'returned' => 'status-chip--returned',
        'approved' => 'status-chip--approved',
        'locked' => 'status-chip--locked',
        'finalized' => 'status-chip--finalized',
        'stale' => 'status-chip--stale',
        'blocked' => 'status-chip--blocked',
        'inactive' => 'status-chip--inactive',
        'active' => 'status-chip--active',
    ];

    $toneClasses = [
        'emerald' => 'status-chip--emerald',
        'amber' => 'status-chip--amber',
        'rose' => 'status-chip--rose',
        'sky' => 'status-chip--sky',
        'slate' => 'status-chip--slate',
        'violet' => 'status-chip--violet',
        'teal' => 'status-chip--teal',
        'blocked' => 'status-chip--blocked',
        'inactive' => 'status-chip--inactive',
    ];

    $variantClass = $stateClasses[$state] ?? $toneClasses[$tone ?? 'slate'] ?? $toneClasses['slate'];
@endphp

<span {{ $attributes->class([
    'status-chip',
    $variantClass,
]) }}>
    <span class="status-chip__dot" aria-hidden="true"></span>
    <span>{{ $slot }}</span>
</span>
