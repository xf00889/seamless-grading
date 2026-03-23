<?php

namespace App\View\Components;

use App\Support\SidebarNavigation;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function __construct(
        private readonly SidebarNavigation $sidebarNavigation,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $user = auth()->user();
        $sidebarGroups = $user ? $this->sidebarNavigation->forUser($user) : [];
        $flatNavigationItems = collect($sidebarGroups)
            ->flatMap(fn (array $group) => $group['items']);
        $currentNavigationItem = $flatNavigationItems->firstWhere('active', true);

        if ($currentNavigationItem === null) {
            $routeName = request()->route()?->getName();

            $currentNavigationItem = $routeName !== null
                ? [
                    'label' => Str::headline(str_replace('.', ' ', $routeName)),
                    'description' => null,
                ]
                : null;
        }

        return view('layouts.app', [
            'currentUserRole' => $user?->getRoleNames()->first()
                ? Str::headline($user->getRoleNames()->first())
                : null,
            'sidebarGroups' => $sidebarGroups,
            'currentNavigationItem' => $currentNavigationItem,
            'userInitials' => $user
                ? Str::of($user->name)
                    ->explode(' ')
                    ->filter()
                    ->take(2)
                    ->map(fn (string $part): string => mb_substr($part, 0, 1))
                    ->implode('')
                : null,
        ]);
    }
}
