<?php

namespace App\Http\Controllers\Admin\UserManagement;

use App\Actions\Admin\UserManagement\TeacherLoads\CreateTeacherLoadAction;
use App\Actions\Admin\UserManagement\TeacherLoads\DeleteTeacherLoadAction;
use App\Actions\Admin\UserManagement\TeacherLoads\SetTeacherLoadActivationAction;
use App\Actions\Admin\UserManagement\TeacherLoads\UpdateTeacherLoadAction;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserManagement\TeacherLoadRequest;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherLoad;
use App\Models\User;
use App\Support\UserManagement\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeacherLoadController extends Controller
{
    public function __construct(private readonly Navigation $navigation)
    {
        $this->authorizeResource(TeacherLoad::class, 'teacher_load');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $schoolYearId = $request->integer('school_year_id');
        $teacherId = $request->integer('teacher_id');
        $sectionId = $request->integer('section_id');
        $subjectId = $request->integer('subject_id');
        $status = (string) $request->string('status');

        $teacherLoads = TeacherLoad::query()
            ->with([
                'schoolYear',
                'teacher.roles',
                'section.gradeLevel',
                'section.adviser',
                'subject',
            ])
            ->withCount('gradeSubmissions')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->whereHas('teacher', fn ($teacherQuery) => $teacherQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%'))
                        ->orWhereHas('section', fn ($sectionQuery) => $sectionQuery
                            ->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('subject', fn ($subjectQuery) => $subjectQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('code', 'like', '%'.$search.'%'))
                        ->orWhereHas('schoolYear', fn ($schoolYearQuery) => $schoolYearQuery
                            ->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($schoolYearId !== 0, fn ($query) => $query->where('school_year_id', $schoolYearId))
            ->when($teacherId !== 0, fn ($query) => $query->where('teacher_id', $teacherId))
            ->when($sectionId !== 0, fn ($query) => $query->where('section_id', $sectionId))
            ->when($subjectId !== 0, fn ($query) => $query->where('subject_id', $subjectId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderByDesc('school_year_id')
            ->orderBy('section_id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.user-management.teacher-loads.index', [
            'navigationItems' => $this->navigation->items(),
            'teacherLoads' => $teacherLoads,
            'filters' => [
                'search' => $search,
                'school_year_id' => $schoolYearId,
                'teacher_id' => $teacherId,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'status' => $status,
            ],
            ...$this->formOptions(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.user-management.teacher-loads.create', [
            'navigationItems' => $this->navigation->items(),
            'teacherLoad' => new TeacherLoad,
            'selectedSchoolYearId' => $request->integer('school_year_id'),
            ...$this->formOptions(),
        ]);
    }

    public function store(TeacherLoadRequest $request, CreateTeacherLoadAction $action): RedirectResponse
    {
        $teacherLoad = $action->handle($request->validated());

        return redirect()
            ->route('admin.user-management.teacher-loads.show', $teacherLoad)
            ->with('status', 'Teacher load assigned successfully.');
    }

    public function show(TeacherLoad $teacherLoad): View
    {
        $teacherLoad->loadCount(['gradeSubmissions', 'gradingSheetExports']);
        $teacherLoad->load([
            'schoolYear',
            'teacher.roles',
            'section.gradeLevel',
            'section.adviser',
            'subject',
            'gradeSubmissions.gradingPeriod',
        ]);

        return view('admin.user-management.teacher-loads.show', [
            'navigationItems' => $this->navigation->items(),
            'teacherLoad' => $teacherLoad,
        ]);
    }

    public function edit(TeacherLoad $teacherLoad): View
    {
        return view('admin.user-management.teacher-loads.edit', [
            'navigationItems' => $this->navigation->items(),
            'teacherLoad' => $teacherLoad,
            'selectedSchoolYearId' => $teacherLoad->school_year_id,
            ...$this->formOptions(),
        ]);
    }

    public function update(
        TeacherLoadRequest $request,
        TeacherLoad $teacherLoad,
        UpdateTeacherLoadAction $action,
    ): RedirectResponse {
        $action->handle($teacherLoad, $request->validated());

        return redirect()
            ->route('admin.user-management.teacher-loads.show', $teacherLoad)
            ->with('status', 'Teacher load updated successfully.');
    }

    public function destroy(
        TeacherLoad $teacherLoad,
        DeleteTeacherLoadAction $action,
    ): RedirectResponse {
        $action->handle($teacherLoad);

        return redirect()
            ->route('admin.user-management.teacher-loads.index')
            ->with('status', 'Teacher load deleted successfully.');
    }

    public function activate(
        TeacherLoad $teacherLoad,
        SetTeacherLoadActivationAction $action,
    ): RedirectResponse {
        $this->authorize('activate', $teacherLoad);
        $action->handle($teacherLoad, true);

        return redirect()
            ->route('admin.user-management.teacher-loads.show', $teacherLoad)
            ->with('status', 'Teacher load activated successfully.');
    }

    public function deactivate(
        TeacherLoad $teacherLoad,
        SetTeacherLoadActivationAction $action,
    ): RedirectResponse {
        $this->authorize('deactivate', $teacherLoad);
        $action->handle($teacherLoad, false);

        return redirect()
            ->route('admin.user-management.teacher-loads.show', $teacherLoad)
            ->with('status', 'Teacher load deactivated successfully.');
    }

    private function formOptions(): array
    {
        return [
            'schoolYears' => SchoolYear::query()->orderByDesc('is_active')->orderByDesc('starts_on')->get(),
            'teachers' => User::query()
                ->role(RoleName::Teacher->value)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'sections' => Section::query()
                ->with(['schoolYear', 'gradeLevel', 'adviser'])
                ->orderByDesc('school_year_id')
                ->orderBy('name')
                ->get(),
            'subjects' => Subject::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ];
    }
}
