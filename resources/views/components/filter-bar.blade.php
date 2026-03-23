@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->class('filter-bar') }}>
    @if ($title || $description || isset($actions))
        <div class="filter-bar__header">
            <div>
                @if ($title)
                    <h2 class="filter-bar__title">{{ $title }}</h2>
                @endif

                @if ($description)
                    <p class="filter-bar__description">{{ $description }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="filter-bar__actions">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    <div class="filter-bar__body">
        {{ $slot }}
    </div>
</section>
