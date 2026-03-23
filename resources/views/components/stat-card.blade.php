@props([
    'label',
    'value',
    'description' => null,
    'tone' => 'default',
    'icon' => null,
    'status' => null,
    'statusTone' => 'slate',
    'actionLabel' => null,
    'actionHref' => null,
])

<article {{ $attributes->class([
    'stat-card',
    'stat-card--'.$tone,
]) }}>
    <div class="stat-card__header">
        <div class="stat-card__header-content">
            <p class="stat-card__label">{{ $label }}</p>

            @if ($status)
                <div class="stat-card__status">
                    <x-status-chip :tone="$statusTone">
                        {{ $status }}
                    </x-status-chip>
                </div>
            @endif
        </div>

        @if ($icon)
            <span class="stat-card__icon" aria-hidden="true">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif
    </div>

    <div class="stat-card__body">
        <p class="stat-card__value">{{ $value }}</p>
    </div>

    @if ($description || trim($slot) !== '' || ($actionLabel && $actionHref) || isset($meta))
        <div class="stat-card__footer">
            @if ($description || trim($slot) !== '')
                <div class="stat-card__description">
                    {{ $description ?? $slot }}
                </div>
            @endif

            @if ($actionLabel && $actionHref)
                <a href="{{ $actionHref }}" class="stat-card__action">
                    {{ $actionLabel }}
                </a>
            @endif

            @isset($meta)
                <div class="stat-card__meta">
                    {{ $meta }}
                </div>
            @endisset
        </div>
    @endif
</article>
