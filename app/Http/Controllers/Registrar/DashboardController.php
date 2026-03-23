<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Services\RegistrarRecords\RegistrarDashboardReadService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RegistrarDashboardReadService $dashboardReadService,
    ) {}

    public function __invoke(): View
    {
        return view('registrar.dashboard', $this->dashboardReadService->build());
    }
}
