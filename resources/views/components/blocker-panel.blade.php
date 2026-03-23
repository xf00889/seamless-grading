@props([
    'tone' => 'amber',
    'title' => 'Blocked',
])

<aside {{ $attributes->class([
    'blocker-panel',
    'blocker-panel--'.$tone,
]) }}>
    <div class="blocker-panel__icon" aria-hidden="true">
        <x-icon name="monitor" class="h-5 w-5" />
    </div>

    <div class="blocker-panel__content">
        <p class="blocker-panel__title">{{ $title }}</p>
        <div class="blocker-panel__body">
            {{ $slot }}
        </div>
    </div>
</aside>
