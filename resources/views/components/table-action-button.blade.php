@props([
    'href' => null,
    'icon' => 'dashboard',
    'tone' => 'secondary',
    'type' => 'button',
])

@if (filled($href))
    <a
        href="{{ $href }}"
        {{ $attributes->class([
            'table-action-button',
            'table-action-button--'.$tone,
        ]) }}
    >
        <span class="table-action-button__icon" aria-hidden="true">
            <x-icon :name="$icon" class="h-4 w-4" />
        </span>
        <span class="table-action-button__label">{{ $slot }}</span>
    </a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->class([
            'table-action-button',
            'table-action-button--'.$tone,
        ]) }}
    >
        <span class="table-action-button__icon" aria-hidden="true">
            <x-icon :name="$icon" class="h-4 w-4" />
        </span>
        <span class="table-action-button__label">{{ $slot }}</span>
    </button>
@endif
