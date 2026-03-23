<nav class="subnav" aria-label="SF1 import sections">
    <ul class="subnav__list">
        @foreach ($navigationItems as $item)
            @php
                $isActive = collect((array) $item['active'])->contains(fn ($pattern) => request()->routeIs($pattern));
            @endphp
            <li>
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'subnav__link',
                        'is-active' => $isActive,
                    ])
                >
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
