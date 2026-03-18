<aside class="w-full border-b border-slate-200 bg-white lg:flex lg:min-h-screen lg:w-80 lg:flex-col lg:border-b-0 lg:border-r">
    <div class="border-b border-slate-200 px-6 py-6">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-900 text-sm font-semibold uppercase tracking-[0.2em] text-white">
                SG
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900">School Grading Workflow</p>
                @if ($currentUserRole)
                    <p class="mt-1 text-xs font-medium uppercase tracking-[0.18em] text-slate-500">
                        {{ $currentUserRole }}
                    </p>
                @endif
            </div>
        </a>
    </div>

    <div class="px-4 py-6">
        <div class="rounded-2xl bg-slate-50 px-4 py-4">
            <p class="text-sm font-semibold text-slate-900">{{ Auth::user()->name }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ Auth::user()->email }}</p>
        </div>
    </div>

    <nav class="flex-1 px-4 pb-6" aria-label="Primary">
        <ul class="space-y-2">
            @foreach ($sidebarItems as $item)
                <li>
                    <a
                        href="{{ route($item['route']) }}"
                        @class([
                            'sidebar-link',
                            'sidebar-link-active' => $item['active'],
                            'sidebar-link-inactive' => ! $item['active'],
                        ])
                    >
                        <div>
                            <p class="text-sm font-semibold">{{ $item['label'] }}</p>
                            <p @class([
                                'mt-1 text-xs leading-5',
                                'text-slate-300' => $item['active'],
                                'text-slate-500' => ! $item['active'],
                            ])>
                                {{ $item['description'] }}
                            </p>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="border-t border-slate-200 px-4 py-6">
        <a
            href="{{ route('profile.edit') }}"
            class="sidebar-link sidebar-link-inactive"
        >
            <div>
                <p class="text-sm font-semibold">Profile</p>
                <p class="mt-1 text-xs text-slate-500">Manage your account details</p>
            </div>
        </a>

        <form method="POST" action="{{ route('logout') }}" class="mt-2">
            @csrf

            <button
                type="submit"
                class="sidebar-link sidebar-link-inactive w-full text-left"
            >
                <span class="text-sm font-semibold">Log out</span>
            </button>
        </form>
    </div>
</aside>
