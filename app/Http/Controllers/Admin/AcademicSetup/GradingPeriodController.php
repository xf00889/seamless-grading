<?php

namespace App\Http\Controllers\Admin\AcademicSetup;

use App\Actions\Admin\AcademicSetup\GradingPeriods\CreateGradingPeriodAction;
use App\Actions\Admin\AcademicSetup\GradingPeriods\DeleteGradingPeriodAction;
use App\Actions\Admin\AcademicSetup\GradingPeriods\SetGradingPeriodOpenStateAction;
use App\Actions\Admin\AcademicSetup\GradingPeriods\UpdateGradingPeriodAction;
use App\Enums\GradingQuarter;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicSetup\GradingPeriodRequest;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use App\Support\AcademicSetup\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GradingPeriodController extends Controller
{
    public function __construct(private readonly Navigation $navigation)
    {
        $this->authorizeResource(GradingPeriod::class, 'grading_period');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $schoolYearId = $request->filled('school_year_id') ? $request->integer('school_year_id') : null;
        $quarter = $request->filled('quarter') ? $request->integer('quarter') : null;
        $status = (string) $request->string('status');

        $gradingPeriods = GradingPeriod::query()
            ->with('schoolYear')
            ->withCount(['gradeSubmissions', 'gradingSheetExports', 'reportCardRecords'])
            ->when(
                $search !== '',
                fn ($query) => $query->whereHas(
                    'schoolYear',
                    fn ($schoolYearQuery) => $schoolYearQuery->where('name', 'like', '%'.$search.'%'),
                ),
            )
            ->when($schoolYearId !== null, fn ($query) => $query->where('school_year_id', $schoolYearId))
            ->when($quarter !== null, fn ($query) => $query->where('quarter', $quarter))
            ->when($status === 'open', fn ($query) => $query->where('is_open', true))
            ->when($status === 'closed', fn ($query) => $query->where('is_open', false))
            ->orderByDesc('school_year_id')
            ->orderBy('quarter')
            ->paginate(10)
            ->withQueryString();

        return view('admin.academic-setup.grading-periods.index', [
            'navigationItems' => $this->navigation->items(),
            'gradingPeriods' => $gradingPeriods,
            'schoolYears' => SchoolYear::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'quarters' => GradingQuarter::cases(),
            'filters' => [
                'search' => $search,
                'school_year_id' => $schoolYearId,
                'quarter' => $quarter,
                'status' => $status,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.academic-setup.grading-periods.create', [
            'navigationItems' => $this->navigation->items(),
            'gradingPeriod' => new GradingPeriod,
            'schoolYears' => SchoolYear::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'quarters' => GradingQuarter::cases(),
            'selectedSchoolYearId' => $request->filled('school_year_id') ? $request->integer('school_year_id') : null,
        ]);
    }

    public function store(
        GradingPeriodRequest $request,
        CreateGradingPeriodAction $action,
    ): RedirectResponse {
        $gradingPeriod = $action->handle($request->validated());

        return redirect()
            ->route('admin.academic-setup.grading-periods.show', $gradingPeriod)
            ->with('status', 'Grading period created successfully.');
    }

    public function show(GradingPeriod $gradingPeriod): View
    {
        $gradingPeriod->load('schoolYear');
        $gradingPeriod->loadCount(['gradeSubmissions', 'gradingSheetExports', 'reportCardRecords']);

        return view('admin.academic-setup.grading-periods.show', [
            'navigationItems' => $this->navigation->items(),
            'gradingPeriod' => $gradingPeriod,
        ]);
    }

    public function edit(GradingPeriod $gradingPeriod): View
    {
        return view('admin.academic-setup.grading-periods.edit', [
            'navigationItems' => $this->navigation->items(),
            'gradingPeriod' => $gradingPeriod,
            'schoolYears' => SchoolYear::query()->orderByDesc('starts_on')->get(['id', 'name']),
            'quarters' => GradingQuarter::cases(),
            'selectedSchoolYearId' => $gradingPeriod->school_year_id,
        ]);
    }

    public function update(
        GradingPeriodRequest $request,
        GradingPeriod $gradingPeriod,
        UpdateGradingPeriodAction $action,
    ): RedirectResponse {
        $action->handle($gradingPeriod, $request->validated());

        return redirect()
            ->route('admin.academic-setup.grading-periods.show', $gradingPeriod)
            ->with('status', 'Grading period updated successfully.');
    }

    public function destroy(
        GradingPeriod $gradingPeriod,
        DeleteGradingPeriodAction $action,
    ): RedirectResponse {
        $action->handle($gradingPeriod);

        return redirect()
            ->route('admin.academic-setup.grading-periods.index')
            ->with('status', 'Grading period deleted successfully.');
    }

    public function open(
        GradingPeriod $gradingPeriod,
        SetGradingPeriodOpenStateAction $action,
    ): RedirectResponse {
        $this->authorize('open', $gradingPeriod);
        $action->handle($gradingPeriod, true);

        return redirect()
            ->route('admin.academic-setup.grading-periods.show', $gradingPeriod)
            ->with('status', 'Grading period opened successfully.');
    }

    public function close(
        GradingPeriod $gradingPeriod,
        SetGradingPeriodOpenStateAction $action,
    ): RedirectResponse {
        $this->authorize('close', $gradingPeriod);
        $action->handle($gradingPeriod, false);

        return redirect()
            ->route('admin.academic-setup.grading-periods.show', $gradingPeriod)
            ->with('status', 'Grading period closed successfully.');
    }
}
