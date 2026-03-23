<nav class="content-card px-4 py-4" aria-label="Academic setup sections">
    <ul class="flex flex-wrap gap-2">
        @foreach ($navigationItems as $item)
            <li>
                <a
                    href="{{ route($item['route']) }}"
                    @class([
                        'inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold transition',
                        'bg-slate-900 text-white' => request()->routeIs($item['active']),
                        'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900' => ! request()->routeIs($item['active']),
                    ])
                >
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
