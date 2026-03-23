<nav class="subnav" aria-label="Submission monitoring sections">
    <ul class="subnav__list">
        @foreach ($navigationItems as $item)
            <li>
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'subnav__link',
                        'is-active' => request()->routeIs($item['active']),
                    ])
                >
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
