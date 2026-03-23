<?php

namespace App\Http\Controllers\Admin\AcademicSetup;

use App\Actions\Admin\AcademicSetup\GradeLevels\CreateGradeLevelAction;
use App\Actions\Admin\AcademicSetup\GradeLevels\DeleteGradeLevelAction;
use App\Actions\Admin\AcademicSetup\GradeLevels\UpdateGradeLevelAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicSetup\GradeLevelRequest;
use App\Models\GradeLevel;
use App\Support\AcademicSetup\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GradeLevelController extends Controller
{
    public function __construct(private readonly Navigation $navigation)
    {
        $this->authorizeResource(GradeLevel::class, 'grade_level');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));

        $gradeLevels = GradeLevel::query()
            ->withCount('sections')
            ->when(
                $search !== '',
                fn ($query) => $query->where(function ($gradeLevelQuery) use ($search): void {
                    $gradeLevelQuery
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%');
                }),
            )
            ->orderBy('sort_order')
            ->paginate(10)
            ->withQueryString();

        return view('admin.academic-setup.grade-levels.index', [
            'navigationItems' => $this->navigation->items(),
            'gradeLevels' => $gradeLevels,
            'filters' => compact('search'),
        ]);
    }

    public function create(): View
    {
        return view('admin.academic-setup.grade-levels.create', [
            'navigationItems' => $this->navigation->items(),
            'gradeLevel' => new GradeLevel,
        ]);
    }

    public function store(GradeLevelRequest $request, CreateGradeLevelAction $action): RedirectResponse
    {
        $gradeLevel = $action->handle($request->validated());

        return redirect()
            ->route('admin.academic-setup.grade-levels.show', $gradeLevel)
            ->with('status', 'Grade level created successfully.');
    }

    public function show(GradeLevel $gradeLevel): View
    {
        $gradeLevel->loadCount('sections');
        $gradeLevel->load([
            'sections' => fn ($query) => $query->with(['schoolYear', 'adviser'])->orderByDesc('school_year_id')->orderBy('name'),
        ]);

        return view('admin.academic-setup.grade-levels.show', [
            'navigationItems' => $this->navigation->items(),
            'gradeLevel' => $gradeLevel,
        ]);
    }

    public function edit(GradeLevel $gradeLevel): View
    {
        return view('admin.academic-setup.grade-levels.edit', [
            'navigationItems' => $this->navigation->items(),
            'gradeLevel' => $gradeLevel,
        ]);
    }

    public function update(
        GradeLevelRequest $request,
        GradeLevel $gradeLevel,
        UpdateGradeLevelAction $action,
    ): RedirectResponse {
        $action->handle($gradeLevel, $request->validated());

        return redirect()
            ->route('admin.academic-setup.grade-levels.show', $gradeLevel)
            ->with('status', 'Grade level updated successfully.');
    }

    public function destroy(GradeLevel $gradeLevel, DeleteGradeLevelAction $action): RedirectResponse
    {
        $action->handle($gradeLevel);

        return redirect()
            ->route('admin.academic-setup.grade-levels.index')
            ->with('status', 'Grade level deleted successfully.');
    }
}
