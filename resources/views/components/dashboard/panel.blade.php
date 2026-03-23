@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'tone' => 'default',
])

<section {{ $attributes->class([
    'studio-panel',
    'studio-panel--'.$tone,
]) }}>
    @if ($title || isset($actions))
        <div class="studio-panel__header">
            @if ($title)
                <h2 class="studio-panel__title">{{ $title }}</h2>
            @endif

            @isset($actions)
                <div class="studio-panel__actions">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    <div class="studio-panel__body">
        {{ $slot }}
    </div>
</section>
