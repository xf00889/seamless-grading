<?php

namespace App\Http\Controllers\Admin\Sf1Imports;

use App\Actions\Admin\Sf1Imports\ConfirmSf1ImportAction;
use App\Actions\Admin\Sf1Imports\UploadSf1ImportAction;
use App\Enums\ImportBatchRowStatus;
use App\Enums\ImportBatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sf1Imports\ConfirmSf1ImportRequest;
use App\Http\Requests\Sf1Imports\UploadSf1ImportRequest;
use App\Models\ImportBatch;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Support\Sf1Import\Navigation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class ImportBatchController extends Controller
{
    public function __construct(private readonly Navigation $navigation)
    {
        $this->authorizeResource(ImportBatch::class, 'import_batch');
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));
        $schoolYearId = $request->integer('school_year_id');
        $sectionId = $request->integer('section_id');
        $status = (string) $request->string('status');

        $importBatches = ImportBatch::query()
            ->with(['section.schoolYear', 'section.gradeLevel', 'importedBy'])
            ->withCount('rows')
            ->when($search !== '', fn ($query) => $query->where('source_file_name', 'like', '%'.$search.'%'))
            ->when($schoolYearId !== 0, fn ($query) => $query->whereHas('section', fn ($sectionQuery) => $sectionQuery->where('school_year_id', $schoolYearId)))
            ->when($sectionId !== 0, fn ($query) => $query->where('section_id', $sectionId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('admin.sf1-imports.batches.index', [
            'navigationItems' => $this->navigation->items(),
            'importBatches' => $importBatches,
            'filters' => [
                'search' => $search,
                'school_year_id' => $schoolYearId,
                'section_id' => $sectionId,
                'status' => $status,
            ],
            'statusOptions' => ImportBatchStatus::cases(),
            'schoolYears' => SchoolYear::query()->orderByDesc('starts_on')->get(),
            'sections' => Section::query()
                ->with(['schoolYear', 'gradeLevel'])
                ->orderByDesc('school_year_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.sf1-imports.batches.create', [
            'navigationItems' => $this->navigation->items(),
            'sections' => Section::query()
                ->with(['schoolYear', 'gradeLevel', 'adviser'])
                ->orderByDesc('school_year_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(UploadSf1ImportRequest $request, UploadSf1ImportAction $action): RedirectResponse
    {
        $section = Section::query()->findOrFail($request->integer('section_id'));
        $importBatch = $action->handle($request->user(), $section, $request->file('file'));

        return redirect()
            ->route('admin.sf1-imports.show', $importBatch)
            ->with('status', 'SF1 batch uploaded. Review every flagged row before confirming the import.');
    }

    public function show(Request $request, ImportBatch $importBatch): View
    {
        $importBatch->loadCount('rows');
        $importBatch->load(['section.schoolYear', 'section.gradeLevel', 'section.adviser', 'importedBy']);

        $rowStatus = (string) $request->string('row_status');
        $search = trim((string) $request->string('search'));
        $rows = $this->previewRows($importBatch, $rowStatus, $search, $request->integer('page', 1));

        return view('admin.sf1-imports.batches.show', [
            'navigationItems' => $this->navigation->items(),
            'importBatch' => $importBatch,
            'rows' => $rows,
            'filters' => compact('rowStatus', 'search'),
            'rowStatusOptions' => ImportBatchRowStatus::cases(),
            'canConfirm' => $importBatch->status !== ImportBatchStatus::Confirmed
                && $importBatch->rows_count > 0
                && $importBatch->invalid_rows === 0,
        ]);
    }

    public function confirm(
        ConfirmSf1ImportRequest $request,
        ImportBatch $importBatch,
        ConfirmSf1ImportAction $action,
    ): RedirectResponse {
        $action->handle($request->user(), $importBatch);

        return redirect()
            ->route('admin.sf1-imports.show', $importBatch)
            ->with('status', 'SF1 batch confirmed and roster records were imported successfully.');
    }

    private function previewRows(
        ImportBatch $importBatch,
        string $rowStatus,
        string $search,
        int $page,
    ): LengthAwarePaginator {
        $rows = $importBatch->rows()
            ->orderBy('row_number')
            ->get()
            ->filter(function ($row) use ($rowStatus, $search): bool {
                if ($rowStatus !== '' && $row->status->value !== $rowStatus) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $haystack = collect([
                    $row->row_number,
                    $row->normalized_data['lrn'] ?? null,
                    $row->normalized_data['last_name'] ?? null,
                    $row->normalized_data['first_name'] ?? null,
                ])->filter()->implode(' ');

                return str_contains(strtolower($haystack), strtolower($search));
            })
            ->values();

        $perPage = 10;

        return new Paginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => route('admin.sf1-imports.show', $importBatch),
                'query' => request()->query(),
            ],
        );
    }
}
