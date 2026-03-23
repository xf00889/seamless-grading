@props([
    'title' => null,
    'description' => null,
    'eyebrow' => null,
])

<section {{ $attributes->class('section-panel') }}>
    @if ($title || $description || $eyebrow || isset($actions))
        <div class="section-panel__header">
            <div>
                @if ($eyebrow)
                    <p class="section-panel__eyebrow">{{ $eyebrow }}</p>
                @endif

                @if ($title)
                    <h2 class="section-panel__title">{{ $title }}</h2>
                @endif

                @if ($description)
                    <p class="section-panel__description">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="section-panel__actions">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    <div class="section-panel__body">
        {{ $slot }}
    </div>
</section>
