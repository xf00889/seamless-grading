<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="app-shell" data-app-shell>
            <button type="button" class="app-shell__backdrop" data-sidebar-close aria-label="Close navigation"></button>
            @include('layouts.navigation')

            <div class="app-main">
                <div class="app-topbar">
                    <div class="app-topbar__row">
                        <div class="app-topbar__meta">
                            <button type="button" class="app-shell__toggle" data-sidebar-toggle aria-label="Open navigation">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path d="M3 5h14M3 10h14M3 15h14" stroke-linecap="round" />
                                </svg>
                            </button>

                            <div class="app-topbar__context">
                                <p class="app-topbar__eyebrow">{{ $currentUserRole ?? 'Authenticated workspace' }}</p>
                                <div class="app-topbar__identity">
                                    <p class="app-topbar__title">{{ $currentNavigationItem['label'] ?? 'Workspace' }}</p>
                                    @if (! empty($currentNavigationItem['description']))
                                        <p class="app-topbar__description">{{ $currentNavigationItem['description'] }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="app-topbar__actions">
                            <a href="{{ route('profile.edit') }}" class="app-topbar__profile-link">
                                <span class="app-topbar__profile-copy">
                                    <span class="app-topbar__profile-name">{{ Auth::user()->name }}</span>
                                    <span class="app-topbar__profile-email">{{ Auth::user()->email }}</span>
                                </span>
                                <span class="app-topbar__avatar" aria-hidden="true">{{ $userInitials }}</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="app-page-frame">
                    @isset($header)
                        <header class="app-page-header">
                            {{ $header }}
                        </header>
                    @endisset

                    <main class="app-content">
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>
