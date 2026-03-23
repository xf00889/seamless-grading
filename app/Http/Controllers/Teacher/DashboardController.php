<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Teacher\TeacherDashboardReadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TeacherDashboardReadService $dashboardReadService,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return view('teacher.dashboard', $this->dashboardReadService->build($user));
    }
}
