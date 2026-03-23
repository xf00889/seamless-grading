<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\AdvisorySectionIndexRequest;
use App\Services\AdviserReview\AdviserQuarterReviewService;
use App\Support\AdviserReview\Navigation;
use Illuminate\Contracts\View\View;

class AdvisorySectionController extends Controller
{
    public function __construct(
        private readonly AdviserQuarterReviewService $reviewService,
        private readonly Navigation $navigation,
    ) {}

    public function index(AdvisorySectionIndexRequest $request): View
    {
        $data = $this->reviewService->sections($request->user(), $request->validated());

        return view('adviser.sections.index', [
            'navigationItems' => $this->navigation->items(),
            ...$data,
        ]);
    }
}
