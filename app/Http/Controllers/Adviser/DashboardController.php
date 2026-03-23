<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\AdviserDashboardRequest;
use App\Services\AdviserReview\AdviserQuarterReviewService;
use App\Support\AdviserReview\Navigation;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AdviserQuarterReviewService $reviewService,
        private readonly Navigation $navigation,
    ) {}

    public function __invoke(AdviserDashboardRequest $request): View
    {
        $data = $this->reviewService->dashboard($request->user(), $request->validated());

        return view('adviser.dashboard', [
            'navigationItems' => $this->navigation->items(),
            ...$data,
        ]);
    }
}
