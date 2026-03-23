<?php

namespace App\Actions\Adviser\YearEnd;

use App\Enums\ReportCardRecordAuditAction;
use App\Enums\TemplateDocumentType;
use App\Models\ReportCardRecord;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\User;
use App\Services\AdviserSf9\ReportCardRecordAuditLogger;
use App\Services\AdviserYearEnd\Sf10PreviewDataBuilder;
use App\Services\AdviserYearEnd\Sf10WorkbookBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExportSf10RecordAction
{
    public function __construct(
        private readonly Sf10PreviewDataBuilder $previewDataBuilder,
        private readonly Sf10WorkbookBuilder $workbookBuilder,
        private readonly ReportCardRecordAuditLogger $auditLogger,
    ) {}

    public function handle(User $adviser, Section $section, SectionRoster $sectionRoster): ReportCardRecord
    {
        $previewData = $this->previewDataBuilder->build($section, $sectionRoster);

        if (! $previewData['export_ready']) {
            throw ValidationException::withMessages([
                'record' => $previewData['blockers'][0] ?? 'The SF10 record cannot be exported yet.',
            ]);
        }

        $tempFilePath = $this->workbookBuilder->build($previewData);
        $disk = (string) config('sf10_exports.disk', 'local');

        try {
            return DB::transaction(function () use (
                $adviser,
                $section,
                $sectionRoster,
                $previewData,
                $tempFilePath,
                $disk,
            ): ReportCardRecord {
                $finalGradingPeriod = $previewData['year_end']['final_grading_period'];
                $template = $previewData['template']['model'];
                $nextVersion = (int) ReportCardRecord::query()
                    ->where('section_roster_id', $sectionRoster->id)
                    ->where('grading_period_id', $finalGradingPeriod->id)
                    ->where('document_type', TemplateDocumentType::Sf10)
                    ->lockForUpdate()
                    ->max('record_version') + 1;

                $fileName = Str::slug(implode('-', [
                    'sf10',
                    $section->name,
                    $sectionRoster->learner->last_name,
                    $section->schoolYear->name,
                ])).'-v'.$nextVersion.'-'.Str::uuid().'.xlsx';
                $filePath = trim((string) config('sf10_exports.directory', 'exports/sf10'), '/')
                    .'/'.$section->id.'/'.$sectionRoster->id.'/'.$fileName;

                Storage::disk($disk)->put($filePath, file_get_contents($tempFilePath));

                $record = ReportCardRecord::query()->create([
                    'section_roster_id' => $sectionRoster->id,
                    'section_id' => $section->id,
                    'school_year_id' => $section->school_year_id,
                    'learner_id' => $sectionRoster->learner_id,
                    'grading_period_id' => $finalGradingPeriod->id,
                    'document_type' => TemplateDocumentType::Sf10,
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
                        'learner' => $previewData['year_end']['learner'],
                        'year_end_status' => $previewData['year_end']['year_end_status'],
                        'subject_rows' => $previewData['year_end']['approved_year_end_rows'],
                        'general_average' => $previewData['year_end']['general_average'],
                    ],
                    'generated_at' => now(),
                ]);

                $this->auditLogger->log(
                    $record,
                    $adviser,
                    ReportCardRecordAuditAction::Exported,
                    'Adviser exported an SF10 draft record.',
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
}
