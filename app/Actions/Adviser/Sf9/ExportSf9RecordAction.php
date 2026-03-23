<?php

namespace App\Actions\Adviser\Sf9;

use App\Enums\ReportCardRecordAuditAction;
use App\Enums\TemplateDocumentType;
use App\Models\GradingPeriod;
use App\Models\ReportCardRecord;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\User;
use App\Services\AdviserSf9\ReportCardRecordAuditLogger;
use App\Services\AdviserSf9\Sf9PreviewDataBuilder;
use App\Services\AdviserSf9\Sf9WorkbookBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExportSf9RecordAction
{
    public function __construct(
        private readonly Sf9PreviewDataBuilder $previewDataBuilder,
        private readonly Sf9WorkbookBuilder $workbookBuilder,
        private readonly ReportCardRecordAuditLogger $auditLogger,
    ) {}

    public function handle(
        User $adviser,
        Section $section,
        GradingPeriod $gradingPeriod,
        SectionRoster $sectionRoster,
    ): ReportCardRecord {
        $previewData = $this->previewDataBuilder->build($section, $gradingPeriod, $sectionRoster);
        $this->ensureExportable($previewData);

        $tempFilePath = $this->workbookBuilder->build($previewData);
        $disk = (string) config('sf9_exports.disk', 'local');

        try {
            return DB::transaction(function () use (
                $adviser,
                $section,
                $gradingPeriod,
                $sectionRoster,
                $previewData,
                $tempFilePath,
                $disk,
            ): ReportCardRecord {
                $template = $previewData['template']['model'];
                $nextVersion = (int) ReportCardRecord::query()
                    ->where('section_roster_id', $sectionRoster->id)
                    ->where('grading_period_id', $gradingPeriod->id)
                    ->where('document_type', TemplateDocumentType::Sf9)
                    ->lockForUpdate()
                    ->max('record_version') + 1;

                $fileName = Str::slug(implode('-', [
                    'sf9',
                    $section->name,
                    $sectionRoster->learner->last_name,
                    $gradingPeriod->quarter->value,
                ])).'-v'.$nextVersion.'-'.Str::uuid().'.xlsx';
                $filePath = trim((string) config('sf9_exports.directory', 'exports/sf9'), '/')
                    .'/'.$section->id.'/'.$gradingPeriod->id.'/'.$sectionRoster->id.'/'.$fileName;

                Storage::disk($disk)->put($filePath, file_get_contents($tempFilePath));

                $record = ReportCardRecord::query()->create([
                    'section_roster_id' => $sectionRoster->id,
                    'section_id' => $section->id,
                    'school_year_id' => $section->school_year_id,
                    'learner_id' => $sectionRoster->learner_id,
                    'grading_period_id' => $gradingPeriod->id,
                    'document_type' => TemplateDocumentType::Sf9,
                    'template_id' => $template->id,
                    'generated_by' => $adviser->id,
                    'record_version' => $nextVersion,
                    'template_version' => $template->version,
                    'file_name' => $fileName,
                    'file_disk' => $disk,
                    'file_path' => $filePath,
                    'is_finalized' => false,
                    'payload' => [
                        'source_hash' => $previewData['source_hash'],
                        'school_name' => $previewData['school_name'],
                        'learner' => $previewData['learner'],
                        'subject_rows' => $previewData['subject_rows'],
                        'general_average' => $previewData['general_average'],
                        'promotion_remarks' => $previewData['promotion_remarks'],
                    ],
                    'generated_at' => now(),
                ]);

                $this->auditLogger->log(
                    $record,
                    $adviser,
                    ReportCardRecordAuditAction::Exported,
                    'Adviser exported an SF9 record.',
                );

                return $record->fresh([
                    'template',
                    'generatedBy',
                    'sectionRoster.learner',
                    'auditLogs',
                ]);
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
            'record' => $previewData['blockers'][0] ?? 'The SF9 record cannot be exported yet.',
        ]);
    }
}
