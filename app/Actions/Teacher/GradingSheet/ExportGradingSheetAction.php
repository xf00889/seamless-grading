<?php

namespace App\Actions\Teacher\GradingSheet;

use App\Enums\GradingSheetExportAuditAction;
use App\Models\GradingPeriod;
use App\Models\GradingSheetExport;
use App\Models\TeacherLoad;
use App\Models\User;
use App\Services\TeacherGradingSheet\GradingSheetExportAuditLogger;
use App\Services\TeacherGradingSheet\GradingSheetPreviewDataBuilder;
use App\Services\TeacherGradingSheet\GradingSheetWorkbookBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExportGradingSheetAction
{
    public function __construct(
        private readonly GradingSheetPreviewDataBuilder $previewDataBuilder,
        private readonly GradingSheetWorkbookBuilder $workbookBuilder,
        private readonly GradingSheetExportAuditLogger $auditLogger,
    ) {}

    public function handle(User $teacher, TeacherLoad $teacherLoad, GradingPeriod $gradingPeriod): GradingSheetExport
    {
        $previewData = $this->previewDataBuilder->build($teacherLoad, $gradingPeriod);
        $this->ensureExportable($previewData);

        $tempFilePath = $this->workbookBuilder->build($previewData);
        $disk = (string) config('grading_sheet_exports.disk', 'local');

        try {
            return DB::transaction(function () use (
                $teacher,
                $teacherLoad,
                $gradingPeriod,
                $previewData,
                $tempFilePath,
                $disk,
            ): GradingSheetExport {
                $template = $previewData['template']['model'];
                $nextVersion = (int) GradingSheetExport::query()
                    ->where('teacher_load_id', $teacherLoad->id)
                    ->where('grading_period_id', $gradingPeriod->id)
                    ->lockForUpdate()
                    ->max('version') + 1;

                $fileName = Str::slug(implode('-', [
                    $teacherLoad->subject->code,
                    $teacherLoad->section->name,
                    $gradingPeriod->quarter->value,
                ])).'-v'.$nextVersion.'-'.Str::uuid().'.xlsx';
                $filePath = trim((string) config('grading_sheet_exports.directory', 'exports/grading-sheets'), '/')
                    .'/'.$teacherLoad->id.'/'.$gradingPeriod->id.'/'.$fileName;

                Storage::disk($disk)->put($filePath, file_get_contents($tempFilePath));

                $gradingSheetExport = GradingSheetExport::query()->create([
                    'teacher_load_id' => $teacherLoad->id,
                    'grading_period_id' => $gradingPeriod->id,
                    'template_id' => $template->id,
                    'exported_by' => $teacher->id,
                    'version' => $nextVersion,
                    'template_version' => $template->version,
                    'file_name' => $fileName,
                    'file_disk' => $disk,
                    'file_path' => $filePath,
                    'exported_at' => now(),
                ]);

                $this->auditLogger->log(
                    $gradingSheetExport,
                    $teacher,
                    GradingSheetExportAuditAction::Exported,
                    'Teacher exported a grading sheet.',
                    [
                        'submission_status' => $previewData['submission']['status']['value'],
                        'template_name' => $template->name,
                    ],
                );

                return $gradingSheetExport->fresh(['template', 'exportedBy', 'auditLogs']);
            });
        } finally {
            if (is_file($tempFilePath)) {
                @unlink($tempFilePath);
            }
        }
    }

    private function ensureExportable(array $previewData): void
    {
        if ($previewData['export_ready']) {
            return;
        }

        throw ValidationException::withMessages([
            'record' => $previewData['blockers'][0] ?? 'The grading sheet cannot be exported yet.',
        ]);
    }
}
