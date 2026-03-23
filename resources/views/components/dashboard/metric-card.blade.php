@props([
    'eyebrow' => null,
    'label',
    'value',
    'description' => null,
    'icon' => null,
    'tone' => 'default',
    'status' => null,
    'statusTone' => 'slate',
    'actionLabel' => null,
    'actionHref' => null,
])

@php
    $hasAction = filled($actionHref);
@endphp

<article {{ $attributes->class([
    'studio-metric',
    'studio-metric--'.$tone,
]) }}>
    @if ($icon)
        <span class="studio-metric__icon" aria-hidden="true">
            <x-icon :name="$icon" class="h-4 w-4" />
        </span>
    @endif

    <div class="studio-metric__body">
        <div class="studio-metric__header">
            <p class="studio-metric__label">{{ $label }}</p>

            @if ($status)
                <span class="studio-metric__status studio-metric__status--{{ $statusTone }}">
                    {{ $status }}
                </span>
            @endif
        </div>

        <p class="studio-metric__value">{{ $value }}</p>
    </div>

    @if ($hasAction)
        <a
            href="{{ $actionHref }}"
            class="studio-metric__action"
            aria-label="{{ $actionLabel ?? $label }}"
            title="{{ $actionLabel ?? $label }}"
        >
            <span aria-hidden="true">↗</span>
        </a>
    @endif
</article>
