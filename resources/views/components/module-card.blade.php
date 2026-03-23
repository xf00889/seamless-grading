@props([
    'title',
    'description' => null,
    'icon' => 'dashboard',
    'href' => null,
    'meta' => null,
    'badge' => null,
])

@php
    $tag = $href ? 'a' : 'article';
@endphp

<{{ $tag }}
    @if ($href)
        href="{{ $href }}"
    @endif
    {{ $attributes->class([
        'module-card',
        'module-card--interactive' => filled($href),
    ]) }}
>
    <div class="module-card__icon" aria-hidden="true">
        <x-icon :name="$icon" class="h-5 w-5" />
    </div>

    <div class="module-card__body">
        <div class="module-card__header">
            <h3 class="module-card__title">{{ $title }}</h3>

            @if ($badge)
                <span class="module-card__badge">{{ $badge }}</span>
            @endif
        </div>

        @if ($meta)
            <p class="module-card__meta">{{ $meta }}</p>
        @endif
    </div>

    @if ($href)
        <span class="module-card__arrow" aria-hidden="true">↗</span>
    @endif
</{{ $tag }}>
