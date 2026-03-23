@props([
    'title' => null,
    'description' => null,
    'count' => null,
])

<section {{ $attributes->class('table-panel') }}>
    @if ($title || $description || $count !== null || isset($actions))
        <div class="table-panel__header">
            <div>
                @if ($title)
                    <h2 class="table-panel__title">{{ $title }}</h2>
                @endif

                @if ($description)
                    <p class="table-panel__description">{{ $description }}</p>
                @endif
            </div>

            @if ($count !== null || isset($actions))
                <div class="table-panel__meta">
                    @if ($count !== null)
                        <span class="table-panel__count">{{ $count }}</span>
                    @endif

                    @isset($actions)
                        <div class="table-panel__actions">
                            {{ $actions }}
                        </div>
                    @endisset
                </div>
            @endif
        </div>
    @endif

    <div class="table-panel__body">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="table-panel__footer">
            <div class="pagination-shell">
                {{ $footer }}
            </div>
        </div>
    @endisset
</section>
