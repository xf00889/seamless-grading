<aside class="app-sidebar" aria-label="Application navigation">
    <div class="app-sidebar__panel">
        <div class="app-sidebar__brand">
            <a href="{{ route('dashboard') }}" class="app-brand">
                <div class="app-brand__mark">SG</div>
                <div>
                    <p class="app-brand__title">School Grading Workflow</p>
                    <p class="app-brand__subtitle">Role-aware workspace</p>
                </div>
            </a>

            <button type="button" class="app-shell__close lg:hidden" data-sidebar-close aria-label="Close navigation">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path d="m5 5 10 10M15 5 5 15" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="app-sidebar__user">
            <div class="app-user-card">
                <div class="app-user-card__identity">
                    <div class="app-user-card__avatar" aria-hidden="true">
                        {{ $userInitials }}
                    </div>
                    <div>
                        <p class="app-user-card__name">{{ Auth::user()->name }}</p>
                        <p class="app-user-card__email">{{ Auth::user()->email }}</p>
                    </div>
                </div>
                @if ($currentUserRole)
                    <div class="app-user-card__meta">
                        <span class="status-chip status-chip--sky">
                            <span class="status-chip__dot" aria-hidden="true"></span>
                            <span>{{ $currentUserRole }}</span>
                        </span>
                    </div>
                @endif
            </div>
        </div>

        <nav class="app-sidebar__nav" aria-label="Primary">
            @foreach ($sidebarGroups as $group)
                <section class="app-nav-group" aria-label="{{ $group['label'] }}">
                    <p class="app-nav-group__label">{{ $group['label'] }}</p>
                    <ul class="app-nav-group__list">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a
                                    href="{{ route($item['route']) }}"
                                    @class([
                                        'sidebar-link',
                                        'sidebar-link-active' => $item['active'],
                                        'sidebar-link-inactive' => ! $item['active'],
                                    ])
                                >
                                    <div class="sidebar-link__icon" aria-hidden="true">
                                        <x-icon :name="$item['icon']" class="h-5 w-5" />
                                    </div>

                                    <div class="sidebar-link__body">
                                        <p class="sidebar-link__label">{{ $item['label'] }}</p>
                                        <p class="sidebar-link__description">{{ $item['description'] }}</p>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </nav>

        <div class="app-sidebar__footer">
            <a href="{{ route('profile.edit') }}" class="sidebar-link sidebar-link-inactive">
                <div class="sidebar-link__icon" aria-hidden="true">
                    <x-icon name="users" class="h-5 w-5" />
                </div>
                <div class="sidebar-link__body">
                    <p class="sidebar-link__label">Profile</p>
                    <p class="sidebar-link__description">Manage your account details</p>
                </div>
            </a>

            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf

                <button type="submit" class="sidebar-link sidebar-link-inactive w-full text-left">
                    <span class="sidebar-link__icon" aria-hidden="true">
                        <x-icon name="undo" class="h-5 w-5" />
                    </span>
                    <div class="sidebar-link__body">
                        <p class="sidebar-link__label">Log out</p>
                        <p class="sidebar-link__description">Sign out of this workspace</p>
                    </div>
                </button>
            </form>
        </div>
    </div>
</aside>
