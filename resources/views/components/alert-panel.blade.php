@props([
    'tone' => 'slate',
    'title' => null,
    'icon' => null,
])

<aside {{ $attributes->class([
    'alert-panel',
    'alert-panel--'.$tone,
]) }}>
    @if ($icon)
        <div class="alert-panel__icon" aria-hidden="true">
            <x-icon :name="$icon" class="h-5 w-5" />
        </div>
    @endif

    <div class="alert-panel__content">
        @if ($title)
            <p class="alert-panel__title">{{ $title }}</p>
        @endif

        <div class="alert-panel__body">
            {{ $slot }}
        </div>
    </div>
</aside>
