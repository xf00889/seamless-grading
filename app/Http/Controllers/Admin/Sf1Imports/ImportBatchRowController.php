<?php

namespace App\Http\Controllers\Admin\Sf1Imports;

use App\Actions\Admin\Sf1Imports\ResolveSf1ImportRowAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sf1Imports\ResolveSf1ImportRowRequest;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Services\Sf1Import\Sf1BatchValidator;
use App\Support\Sf1Import\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ImportBatchRowController extends Controller
{
    public function __construct(private readonly Navigation $navigation) {}

    public function edit(
        ImportBatch $importBatch,
        ImportBatchRow $importBatchRow,
        Sf1BatchValidator $validator,
    ): View {
        $this->authorize('resolve', $importBatch);
        $this->ensureRowBelongsToBatch($importBatch, $importBatchRow);

        return view('admin.sf1-imports.batches.edit-row', [
            'navigationItems' => $this->navigation->items(),
            'importBatch' => $importBatch->load(['section.schoolYear', 'section.gradeLevel']),
            'importBatchRow' => $importBatchRow,
            'candidateLearners' => $validator->candidateLearnersForRow($importBatchRow),
        ]);
    }

    public function update(
        ResolveSf1ImportRowRequest $request,
        ImportBatch $importBatch,
        ImportBatchRow $importBatchRow,
        ResolveSf1ImportRowAction $action,
    ): RedirectResponse {
        $this->ensureRowBelongsToBatch($importBatch, $importBatchRow);

        $action->handle($importBatch, $importBatchRow, $request->validated());

        return redirect()
            ->route('admin.sf1-imports.show', $importBatch)
            ->with('status', 'Import row updated and revalidated successfully.');
    }

    private function ensureRowBelongsToBatch(ImportBatch $importBatch, ImportBatchRow $importBatchRow): void
    {
        abort_unless($importBatchRow->import_batch_id === $importBatch->id, 404);
    }
}
