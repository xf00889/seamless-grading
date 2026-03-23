@props([
    'title',
    'description' => null,
    'icon' => 'dashboard',
])

<div {{ $attributes->class('empty-state') }}>
    <div class="empty-state__icon" aria-hidden="true">
        <x-icon :name="$icon" class="h-6 w-6" />
    </div>
    <div class="empty-state__content">
        <h3 class="empty-state__title">{{ $title }}</h3>

        @if ($description || trim($slot) !== '')
            <div class="empty-state__description">
                {{ $description ?? $slot }}
            </div>
        @endif
    </div>

    @isset($actions)
        <div class="empty-state__actions">
            {{ $actions }}
        </div>
    @endisset
</div>
