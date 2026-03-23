<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\Teacher\GradingSheet\ExportGradingSheetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ExportGradingSheetRequest;
use App\Models\GradingPeriod;
use App\Models\GradingSheetExport;
use App\Models\TeacherLoad;
use App\Services\TeacherGradingSheet\GradingSheetPreviewDataBuilder;
use App\Support\TeacherWorkArea\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GradingSheetController extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly GradingSheetPreviewDataBuilder $previewDataBuilder,
    ) {}

    public function show(TeacherLoad $teacherLoad, GradingPeriod $gradingPeriod): View
    {
        $this->authorize('previewGradingSheet', $teacherLoad);

        return view('teacher.grading-sheet.show', [
            'navigationItems' => $this->navigation->items(),
            ...$this->previewDataBuilder->build($teacherLoad, $gradingPeriod),
        ]);
    }

    public function export(
        ExportGradingSheetRequest $request,
        TeacherLoad $teacherLoad,
        GradingPeriod $gradingPeriod,
        ExportGradingSheetAction $action,
    ): StreamedResponse {
        $gradingSheetExport = $action->handle($request->user(), $teacherLoad, $gradingPeriod);

        return Storage::disk($gradingSheetExport->file_disk)
            ->download($gradingSheetExport->file_path, $gradingSheetExport->file_name);
    }

    public function download(
        TeacherLoad $teacherLoad,
        GradingPeriod $gradingPeriod,
        GradingSheetExport $gradingSheetExport,
    ): StreamedResponse {
        $this->authorize('download', $gradingSheetExport);

        abort_unless($teacherLoad->school_year_id === $gradingPeriod->school_year_id, 404);
        abort_unless(
            $gradingSheetExport->teacher_load_id === $teacherLoad->id
            && $gradingSheetExport->grading_period_id === $gradingPeriod->id,
            404,
        );
        abort_unless(Storage::disk($gradingSheetExport->file_disk)->exists($gradingSheetExport->file_path), 404);

        return Storage::disk($gradingSheetExport->file_disk)
            ->download($gradingSheetExport->file_path, $gradingSheetExport->file_name);
    }
}
