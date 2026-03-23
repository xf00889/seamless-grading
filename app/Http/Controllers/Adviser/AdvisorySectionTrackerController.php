<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\AdvisorySectionTrackerRequest;
use App\Models\GradingPeriod;
use App\Models\Section;
use App\Services\AdviserReview\AdviserQuarterReviewService;
use App\Support\AdviserReview\Navigation;
use Illuminate\Contracts\View\View;

class AdvisorySectionTrackerController extends Controller
{
    public function __construct(
        private readonly AdviserQuarterReviewService $reviewService,
        private readonly Navigation $navigation,
    ) {}

    public function show(
        AdvisorySectionTrackerRequest $request,
        Section $section,
        GradingPeriod $gradingPeriod,
    ): View {
        $this->authorize('viewAsAdviser', $section);

        $data = $this->reviewService->tracker($section, $gradingPeriod, $request->validated());

        return view('adviser.sections.tracker', [
            'navigationItems' => $this->navigation->items(),
            ...$data,
        ]);
    }
}
