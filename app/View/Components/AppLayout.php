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

        return view('layouts.app', [
            'currentUserRole' => $user?->getRoleNames()->first()
                ? Str::headline($user->getRoleNames()->first())
                : null,
            'sidebarItems' => $user ? $this->sidebarNavigation->forUser($user) : [],
        ]);
    }
}
