<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\Adviser\YearEnd\UpdateLearnerYearEndStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\UpdateYearEndLearnerStatusRequest;
use App\Http\Requests\Adviser\YearEndLearnerStatusIndexRequest;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Services\AdviserYearEnd\YearEndLearnerStatusReadService;
use App\Support\AdviserReview\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AdvisorySectionYearEndController extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly YearEndLearnerStatusReadService $readService,
    ) {}

    public function index(Section $section, YearEndLearnerStatusIndexRequest $request): View
    {
        return view('adviser.year-end.index', [
            'navigationItems' => $this->navigation->items(),
            ...$this->readService->build($section, $request->validated()),
        ]);
    }

    public function update(
        Section $section,
        SectionRoster $sectionRoster,
        UpdateYearEndLearnerStatusRequest $request,
        UpdateLearnerYearEndStatusAction $action,
    ): RedirectResponse {
        $validated = $request->validated();

        $action->handle($request->user(), $section, $sectionRoster, $validated);

        $routeParameters = array_filter([
            'section' => $section,
            'search' => $validated['search_filter'] ?? null,
            'status' => $validated['status_filter'] ?? null,
        ], static fn (mixed $value): bool => ! blank($value));

        return redirect()
            ->route('adviser.sections.year-end.index', $routeParameters)
            ->with('status', 'Learner year-end status updated successfully.');
    }
}
