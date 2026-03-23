<?php

namespace App\Http\Controllers\Admin\AcademicSetup;

use App\Actions\Admin\AcademicSetup\SchoolYears\CreateSchoolYearAction;
use App\Actions\Admin\AcademicSetup\SchoolYears\DeleteSchoolYearAction;
use App\Actions\Admin\AcademicSetup\SchoolYears\SetSchoolYearActivationAction;
use App\Actions\Admin\AcademicSetup\SchoolYears\UpdateSchoolYearAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicSetup\SchoolYearRequest;
use App\Models\SchoolYear;
use App\Support\AcademicSetup\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SchoolYearController extends Controller
{
    public function __construct(private readonly Navigation $navigation)
    {
        $this->authorizeResource(SchoolYear::class, 'school_year');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $schoolYears = SchoolYear::query()
            ->withCount(['gradingPeriods', 'sections'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderByDesc('starts_on')
            ->paginate(10)
            ->withQueryString();

        return view('admin.academic-setup.school-years.index', [
            'navigationItems' => $this->navigation->items(),
            'schoolYears' => $schoolYears,
            'filters' => compact('search', 'status'),
        ]);
    }

    public function create(): View
    {
        return view('admin.academic-setup.school-years.create', [
            'navigationItems' => $this->navigation->items(),
            'schoolYear' => new SchoolYear,
        ]);
    }

    public function store(SchoolYearRequest $request, CreateSchoolYearAction $action): RedirectResponse
    {
        $schoolYear = $action->handle($request->validated());

        return redirect()
            ->route('admin.academic-setup.school-years.show', $schoolYear)
            ->with('status', 'School year created successfully.');
    }

    public function show(SchoolYear $schoolYear): View
    {
        $schoolYear->loadCount(['gradingPeriods', 'sections']);
        $schoolYear->load([
            'gradingPeriods' => fn ($query) => $query->orderBy('quarter'),
            'sections' => fn ($query) => $query->with(['gradeLevel', 'adviser'])->orderBy('name'),
        ]);

        return view('admin.academic-setup.school-years.show', [
            'navigationItems' => $this->navigation->items(),
            'schoolYear' => $schoolYear,
        ]);
    }

    public function edit(SchoolYear $schoolYear): View
    {
        return view('admin.academic-setup.school-years.edit', [
            'navigationItems' => $this->navigation->items(),
            'schoolYear' => $schoolYear,
        ]);
    }

    public function update(
        SchoolYearRequest $request,
        SchoolYear $schoolYear,
        UpdateSchoolYearAction $action,
    ): RedirectResponse {
        $action->handle($schoolYear, $request->validated());

        return redirect()
            ->route('admin.academic-setup.school-years.show', $schoolYear)
            ->with('status', 'School year updated successfully.');
    }

    public function destroy(SchoolYear $schoolYear, DeleteSchoolYearAction $action): RedirectResponse
    {
        $action->handle($schoolYear);

        return redirect()
            ->route('admin.academic-setup.school-years.index')
            ->with('status', 'School year deleted successfully.');
    }

    public function activate(
        SchoolYear $schoolYear,
        SetSchoolYearActivationAction $action,
    ): RedirectResponse {
        $this->authorize('activate', $schoolYear);
        $action->handle($schoolYear, true);

        return redirect()
            ->route('admin.academic-setup.school-years.show', $schoolYear)
            ->with('status', 'School year activated successfully.');
    }

    public function deactivate(
        SchoolYear $schoolYear,
        SetSchoolYearActivationAction $action,
    ): RedirectResponse {
        $this->authorize('deactivate', $schoolYear);
        $action->handle($schoolYear, false);

        return redirect()
            ->route('admin.academic-setup.school-years.show', $schoolYear)
            ->with('status', 'School year deactivated successfully.');
    }
}
