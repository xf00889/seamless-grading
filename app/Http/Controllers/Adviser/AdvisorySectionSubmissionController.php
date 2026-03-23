<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\Adviser\Review\ApproveGradeSubmissionAction;
use App\Actions\Adviser\Review\ReturnGradeSubmissionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\ApproveGradeSubmissionRequest;
use App\Http\Requests\Adviser\ReturnGradeSubmissionRequest;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\Section;
use App\Services\AdviserReview\AdviserQuarterReviewService;
use App\Support\AdviserReview\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AdvisorySectionSubmissionController extends Controller
{
    public function __construct(
        private readonly AdviserQuarterReviewService $reviewService,
        private readonly Navigation $navigation,
    ) {}

    public function show(
        Section $section,
        GradingPeriod $gradingPeriod,
        GradeSubmission $gradeSubmission,
    ): View {
        $this->authorize('viewAsAdviser', $section);
        $this->authorize('viewAsAdviser', $gradeSubmission);

        $data = $this->reviewService->review($section, $gradingPeriod, $gradeSubmission);

        return view('adviser.submissions.show', [
            'navigationItems' => $this->navigation->items(),
            ...$data,
        ]);
    }

    public function approve(
        ApproveGradeSubmissionRequest $request,
        Section $section,
        GradingPeriod $gradingPeriod,
        GradeSubmission $gradeSubmission,
        ApproveGradeSubmissionAction $action,
    ): RedirectResponse {
        $action->handle($request->user(), $section, $gradingPeriod, $gradeSubmission);

        return redirect()
            ->route('adviser.sections.submissions.show', [
                'section' => $section,
                'grading_period' => $gradingPeriod,
                'grade_submission' => $gradeSubmission,
            ])
            ->with('status', 'Submission approved successfully.');
    }

    public function return(
        ReturnGradeSubmissionRequest $request,
        Section $section,
        GradingPeriod $gradingPeriod,
        GradeSubmission $gradeSubmission,
        ReturnGradeSubmissionAction $action,
    ): RedirectResponse {
        $action->handle(
            $request->user(),
            $section,
            $gradingPeriod,
            $gradeSubmission,
            trim((string) $request->validated('remarks')),
        );

        return redirect()
            ->route('adviser.sections.submissions.show', [
                'section' => $section,
                'grading_period' => $gradingPeriod,
                'grade_submission' => $gradeSubmission,
            ])
            ->with('status', 'Submission returned to the teacher successfully.');
    }
}
