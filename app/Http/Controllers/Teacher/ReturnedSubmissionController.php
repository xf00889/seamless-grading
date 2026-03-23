<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\GradeSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ReturnedSubmissionIndexRequest;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use App\Support\TeacherWorkArea\Navigation;
use Illuminate\Contracts\View\View;

class ReturnedSubmissionController extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
    ) {}

    public function index(ReturnedSubmissionIndexRequest $request): View
    {
        $user = $request->user();
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));
        $schoolYearId = isset($filters['school_year_id']) ? (int) $filters['school_year_id'] : null;
        $gradingPeriodId = isset($filters['grading_period_id']) ? (int) $filters['grading_period_id'] : null;

        abort_unless($user !== null, 401);

        $returnedSubmissions = GradeSubmission::query()
            ->with([
                'gradingPeriod.schoolYear',
                'teacherLoad.schoolYear',
                'teacherLoad.section.gradeLevel',
                'teacherLoad.section.adviser',
                'teacherLoad.subject',
            ])
            ->where('status', GradeSubmissionStatus::Returned)
            ->whereHas('teacherLoad', fn ($query) => $query->where('teacher_id', $user->id))
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(function ($submissionQuery) use ($search): void {
                        $submissionQuery
                            ->where('adviser_remarks', 'like', '%'.$search.'%')
                            ->orWhereHas('teacherLoad.subject', fn ($subjectQuery) => $subjectQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('code', 'like', '%'.$search.'%'))
                            ->orWhereHas('teacherLoad.section', fn ($sectionQuery) => $sectionQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhereHas('gradeLevel', fn ($gradeLevelQuery) => $gradeLevelQuery
                                    ->where('name', 'like', '%'.$search.'%'))
                                ->orWhereHas('adviser', fn ($adviserQuery) => $adviserQuery
                                    ->where('name', 'like', '%'.$search.'%')))
                            ->orWhereHas('teacherLoad.schoolYear', fn ($schoolYearQuery) => $schoolYearQuery
                                ->where('name', 'like', '%'.$search.'%'));
                    });
                },
            )
            ->when($schoolYearId !== null, fn ($query) => $query->whereHas(
                'teacherLoad',
                fn ($teacherLoadQuery) => $teacherLoadQuery->where('school_year_id', $schoolYearId),
            ))
            ->when($gradingPeriodId !== null, fn ($query) => $query->where('grading_period_id', $gradingPeriodId))
            ->orderByDesc('returned_at')
            ->paginate(10)
            ->withQueryString();

        return view('teacher.returned-submissions.index', [
            'navigationItems' => $this->navigation->items(),
            'returnedSubmissions' => $returnedSubmissions,
            'schoolYears' => SchoolYear::query()
                ->whereHas('teacherLoads', fn ($query) => $query->where('teacher_id', $user->id))
                ->orderByDesc('starts_on')
                ->get(['id', 'name']),
            'gradingPeriods' => GradingPeriod::query()
                ->with('schoolYear:id,name')
                ->whereHas('gradeSubmissions.teacherLoad', fn ($query) => $query->where('teacher_id', $user->id))
                ->orderByDesc('school_year_id')
                ->orderBy('quarter')
                ->get(['id', 'school_year_id', 'quarter']),
            'filters' => [
                'search' => $search,
                'school_year_id' => $schoolYearId,
                'grading_period_id' => $gradingPeriodId,
            ],
        ]);
    }
}
