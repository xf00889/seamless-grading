<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardReadService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardReadService $adminDashboardReadService,
    ) {}

    public function __invoke(): View
    {
        return view('admin.dashboard', $this->adminDashboardReadService->build());
    }
}
