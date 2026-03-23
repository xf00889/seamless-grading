<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\LearnerMovement\UpdateLearnerMovementExceptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\LearnerMovementIndexRequest;
use App\Http\Requests\Adviser\UpdateLearnerMovementRequest;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Services\LearnerMovement\LearnerMovementReadService;
use App\Support\AdviserReview\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AdvisorySectionLearnerMovementController extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly LearnerMovementReadService $readService,
    ) {}

    public function index(Section $section, LearnerMovementIndexRequest $request): View
    {
        return view('adviser.learner-movements.index', [
            'navigationItems' => $this->navigation->items(),
            ...$this->readService->build($section, $request->validated()),
        ]);
    }

    public function update(
        Section $section,
        SectionRoster $sectionRoster,
        UpdateLearnerMovementRequest $request,
        UpdateLearnerMovementExceptionAction $action,
    ): RedirectResponse {
        $validated = $request->validated();

        $action->handle($request->user(), $section, $sectionRoster, $validated);

        return redirect()
            ->route('adviser.sections.learner-movements.index', array_filter([
                'section' => $section,
                'search' => $validated['search_filter'] ?? null,
                'status' => $validated['status_filter'] ?? null,
            ], static fn (mixed $value): bool => ! blank($value)))
            ->with('status', 'Learner movement exception updated successfully.');
    }
}
