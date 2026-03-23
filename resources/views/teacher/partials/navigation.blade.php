<nav aria-label="Teacher work area" class="subnav">
    <div class="subnav__list">
    @foreach ($navigationItems as $item)
        <a
            href="{{ route($item['route']) }}"
            @class([
                'subnav__link',
                'is-active' => request()->routeIs($item['active']),
            ])
        >
            {{ $item['label'] }}
        </a>
    @endforeach
    </div>
</nav>
