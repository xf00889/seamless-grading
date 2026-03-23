<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\AdvisorySectionConsolidationRequest;
use App\Models\GradingPeriod;
use App\Models\Section;
use App\Services\AdviserReview\AdviserQuarterConsolidationService;
use App\Support\AdviserReview\Navigation;
use Illuminate\Contracts\View\View;

class AdvisorySectionConsolidationController extends Controller
{
    public function __construct(
        private readonly AdviserQuarterConsolidationService $consolidationService,
        private readonly Navigation $navigation,
    ) {}

    public function byLearner(
        AdvisorySectionConsolidationRequest $request,
        Section $section,
        GradingPeriod $gradingPeriod,
    ): View {
        $this->authorize('viewAsAdviser', $section);

        $data = $this->consolidationService->byLearner($section, $gradingPeriod, $request->validated());

        return view('adviser.consolidation.learners', [
            'navigationItems' => $this->navigation->items(),
            ...$data,
        ]);
    }

    public function bySubject(
        AdvisorySectionConsolidationRequest $request,
        Section $section,
        GradingPeriod $gradingPeriod,
    ): View {
        $this->authorize('viewAsAdviser', $section);

        $data = $this->consolidationService->bySubject($section, $gradingPeriod, $request->validated());

        return view('adviser.consolidation.subjects', [
            'navigationItems' => $this->navigation->items(),
            ...$data,
        ]);
    }
}
