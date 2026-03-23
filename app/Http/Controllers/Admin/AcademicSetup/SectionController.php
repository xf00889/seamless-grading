<?php

namespace App\Http\Controllers\Admin\AcademicSetup;

use App\Actions\Admin\AcademicSetup\Sections\CreateSectionAction;
use App\Actions\Admin\AcademicSetup\Sections\DeleteSectionAction;
use App\Actions\Admin\AcademicSetup\Sections\SetSectionActivationAction;
use App\Actions\Admin\AcademicSetup\Sections\UpdateSectionAction;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicSetup\SectionRequest;
use App\Models\GradeLevel;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use App\Support\AcademicSetup\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function __construct(private readonly Navigation $navigation)
    {
        $this->authorizeResource(Section::class, 'section');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $schoolYearId = $request->filled('school_year_id') ? $request->integer('school_year_id') : null;
        $gradeLevelId = $request->filled('grade_level_id') ? $request->integer('grade_level_id') : null;
        $status = (string) $request->string('status');

        $sections = Section::query()
            ->with(['schoolYear', 'gradeLevel', 'adviser'])
            ->withCount(['teacherLoads', 'sectionRosters'])
            ->when(
                $search !== '',
                function ($query) use ($search): void {
                    $query->where(function ($sectionQuery) use ($search): void {
                        $sectionQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhereHas('schoolYear', fn ($schoolYearQuery) => $schoolYearQuery->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('gradeLevel', fn ($gradeLevelQuery) => $gradeLevelQuery->where('name', 'like', '%'.$search.'%'))
                            ->orWhereHas('adviser', fn ($adviserQuery) => $adviserQuery->where('name', 'like', '%'.$search.'%'));
                    });
                },
            )
            ->when($schoolYearId !== null, fn ($query) => $query->where('school_year_id', $schoolYearId))
            ->when($gradeLevelId !== null, fn ($query) => $query->where('grade_level_id', $gradeLevelId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('school_year_id')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.academic-setup.sections.index', [
            'navigationItems' => $this->navigation->items(),
            'sections' => $sections,
            'schoolYears' => SchoolYear::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'gradeLevels' => GradeLevel::query()->orderBy('sort_order')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'school_year_id' => $schoolYearId,
                'grade_level_id' => $gradeLevelId,
                'status' => $status,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.academic-setup.sections.create', [
            'navigationItems' => $this->navigation->items(),
            'section' => new Section,
            'schoolYears' => SchoolYear::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'gradeLevels' => GradeLevel::query()->orderBy('sort_order')->get(['id', 'name']),
            'advisers' => $this->advisers(),
            'selectedSchoolYearId' => $request->filled('school_year_id') ? $request->integer('school_year_id') : null,
            'selectedGradeLevelId' => $request->filled('grade_level_id') ? $request->integer('grade_level_id') : null,
        ]);
    }

    public function store(SectionRequest $request, CreateSectionAction $action): RedirectResponse
    {
        $section = $action->handle($request->validated());

        return redirect()
            ->route('admin.academic-setup.sections.show', $section)
            ->with('status', 'Section created successfully.');
    }

    public function show(Section $section): View
    {
        $section->load(['schoolYear', 'gradeLevel', 'adviser']);
        $section->loadCount(['teacherLoads', 'sectionRosters']);

        return view('admin.academic-setup.sections.show', [
            'navigationItems' => $this->navigation->items(),
            'section' => $section,
        ]);
    }

    public function edit(Section $section): View
    {
        return view('admin.academic-setup.sections.edit', [
            'navigationItems' => $this->navigation->items(),
            'section' => $section,
            'schoolYears' => SchoolYear::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'gradeLevels' => GradeLevel::query()->orderBy('sort_order')->get(['id', 'name']),
            'advisers' => $this->advisers(),
            'selectedSchoolYearId' => $section->school_year_id,
            'selectedGradeLevelId' => $section->grade_level_id,
        ]);
    }

    public function update(
        SectionRequest $request,
        Section $section,
        UpdateSectionAction $action,
    ): RedirectResponse {
        $action->handle($section, $request->validated());

        return redirect()
            ->route('admin.academic-setup.sections.show', $section)
            ->with('status', 'Section updated successfully.');
    }

    public function destroy(Section $section, DeleteSectionAction $action): RedirectResponse
    {
        $action->handle($section);

        return redirect()
            ->route('admin.academic-setup.sections.index')
            ->with('status', 'Section deleted successfully.');
    }

    public function activate(Section $section, SetSectionActivationAction $action): RedirectResponse
    {
        $this->authorize('activate', $section);
        $action->handle($section, true);

        return redirect()
            ->route('admin.academic-setup.sections.show', $section)
            ->with('status', 'Section activated successfully.');
    }

    public function deactivate(Section $section, SetSectionActivationAction $action): RedirectResponse
    {
        $this->authorize('deactivate', $section);
        $action->handle($section, false);

        return redirect()
            ->route('admin.academic-setup.sections.show', $section)
            ->with('status', 'Section deactivated successfully.');
    }

    private function advisers(): Collection
    {
        return User::role(RoleName::Adviser->value)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
