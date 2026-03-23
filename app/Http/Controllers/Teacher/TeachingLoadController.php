<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\SectionLearnerIndexRequest;
use App\Http\Requests\Teacher\TeachingLoadIndexRequest;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use App\Models\SectionRoster;
use App\Models\TeacherLoad;
use App\Support\TeacherWorkArea\Navigation;
use Illuminate\Contracts\View\View;

class TeachingLoadController extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
    ) {}

    public function index(TeachingLoadIndexRequest $request): View
    {
        $user = $request->user();
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));
        $schoolYearId = isset($filters['school_year_id']) ? (int) $filters['school_year_id'] : null;
        $status = (string) ($filters['status'] ?? '');

        abort_unless($user !== null, 401);

        $teacherLoads = TeacherLoad::query()
            ->with([
                'schoolYear',
                'section.gradeLevel',
                'section.adviser',
                'subject',
            ])
            ->withCount([
                'gradeSubmissions as returned_submissions_count' => fn ($query) => $query
                    ->where('status', GradeSubmissionStatus::Returned),
            ])
            ->where('teacher_id', $user->id)
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(function ($teacherLoadQuery) use ($search): void {
                        $teacherLoadQuery
                            ->whereHas('subject', fn ($subjectQuery) => $subjectQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('code', 'like', '%'.$search.'%'))
                            ->orWhereHas('section', fn ($sectionQuery) => $sectionQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhereHas('gradeLevel', fn ($gradeLevelQuery) => $gradeLevelQuery
                                    ->where('name', 'like', '%'.$search.'%'))
                                ->orWhereHas('adviser', fn ($adviserQuery) => $adviserQuery
                                    ->where('name', 'like', '%'.$search.'%')))
                            ->orWhereHas('schoolYear', fn ($schoolYearQuery) => $schoolYearQuery
                                ->where('name', 'like', '%'.$search.'%'));
                    });
                },
            )
            ->when($schoolYearId !== null, fn ($query) => $query->where('school_year_id', $schoolYearId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('school_year_id')
            ->orderByDesc('is_active')
            ->orderBy('section_id')
            ->paginate(10)
            ->withQueryString();

        return view('teacher.loads.index', [
            'navigationItems' => $this->navigation->items(),
            'teacherLoads' => $teacherLoads,
            'schoolYears' => SchoolYear::query()
                ->whereHas('teacherLoads', fn ($query) => $query->where('teacher_id', $user->id))
                ->orderByDesc('starts_on')
                ->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'school_year_id' => $schoolYearId,
                'status' => $status,
            ],
        ]);
    }

    public function show(SectionLearnerIndexRequest $request, TeacherLoad $teacherLoad): View
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));
        $enrollmentStatus = isset($filters['enrollment_status'])
            ? EnrollmentStatus::from($filters['enrollment_status'])
            : null;

        $teacherLoad->load([
            'schoolYear',
            'section.gradeLevel',
            'section.adviser',
            'subject',
        ]);
        $teacherLoad->loadCount([
            'gradeSubmissions as returned_submissions_count' => fn ($query) => $query
                ->where('status', GradeSubmissionStatus::Returned),
        ]);

        $learners = SectionRoster::query()
            ->with('learner')
            ->join('learners', 'learners.id', '=', 'section_rosters.learner_id')
            ->select('section_rosters.*')
            ->where('section_rosters.school_year_id', $teacherLoad->school_year_id)
            ->where('section_rosters.section_id', $teacherLoad->section_id)
            ->where('section_rosters.is_official', true)
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->whereHas('learner', function ($learnerQuery) use ($search): void {
                        $learnerQuery
                            ->where('lrn', 'like', '%'.$search.'%')
                            ->orWhere('last_name', 'like', '%'.$search.'%')
                            ->orWhere('first_name', 'like', '%'.$search.'%')
                            ->orWhere('middle_name', 'like', '%'.$search.'%');
                    });
                },
            )
            ->when(
                $enrollmentStatus !== null,
                fn ($query) => $query->where('section_rosters.enrollment_status', $enrollmentStatus),
            )
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name')
            ->paginate(15)
            ->withQueryString();

        return view('teacher.loads.show', [
            'navigationItems' => $this->navigation->items(),
            'teacherLoad' => $teacherLoad,
            'learners' => $learners,
            'gradingPeriods' => GradingPeriod::query()
                ->with([
                    'gradeSubmissions' => fn ($query) => $query
                        ->where('teacher_load_id', $teacherLoad->id),
                ])
                ->where('school_year_id', $teacherLoad->school_year_id)
                ->orderBy('quarter')
                ->get(),
            'filters' => [
                'search' => $search,
                'enrollment_status' => $enrollmentStatus?->value,
            ],
        ]);
    }
}
