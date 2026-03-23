<nav aria-label="Template management" class="flex flex-wrap gap-2">
    @foreach ($navigationItems as $item)
        <a
            href="{{ route($item['route']) }}"
            @class([
                'inline-flex items-center rounded-full px-4 py-2 text-sm font-semibold transition',
                'bg-slate-900 text-white' => request()->routeIs($item['active']),
                'border border-slate-300 text-slate-700 hover:border-slate-400 hover:text-slate-900' => ! request()->routeIs($item['active']),
            ])
        >
            {{ $item['label'] }}
        </a>
    @endforeach
</nav>
