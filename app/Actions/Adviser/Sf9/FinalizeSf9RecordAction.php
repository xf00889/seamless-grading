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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeSf9RecordAction
{
    public function __construct(
        private readonly Sf9PreviewDataBuilder $previewDataBuilder,
        private readonly ReportCardRecordAuditLogger $auditLogger,
    ) {}

    public function handle(
        User $adviser,
        Section $section,
        GradingPeriod $gradingPeriod,
        SectionRoster $sectionRoster,
    ): ReportCardRecord {
        $previewData = $this->previewDataBuilder->build($section, $gradingPeriod, $sectionRoster);

        if (! $previewData['finalize_ready']) {
            throw ValidationException::withMessages([
                'record' => $previewData['finalization_blockers'][0] ?? 'The SF9 record cannot be finalized yet.',
            ]);
        }

        return DB::transaction(function () use ($adviser, $sectionRoster, $gradingPeriod, $previewData): ReportCardRecord {
            $latestRecord = ReportCardRecord::query()
                ->where('section_roster_id', $sectionRoster->id)
                ->where('grading_period_id', $gradingPeriod->id)
                ->where('document_type', TemplateDocumentType::Sf9)
                ->orderByDesc('record_version')
                ->lockForUpdate()
                ->first();

            if ($latestRecord === null) {
                throw ValidationException::withMessages([
                    'record' => 'Generate an SF9 export version before finalizing this learner record.',
                ]);
            }

            if ((int) $latestRecord->id !== (int) ($previewData['history'][0]['id'] ?? 0)) {
                throw ValidationException::withMessages([
                    'record' => 'A newer SF9 export version is available. Reload the page before finalizing.',
                ]);
            }

            ReportCardRecord::query()
                ->where('section_roster_id', $sectionRoster->id)
                ->where('grading_period_id', $gradingPeriod->id)
                ->where('document_type', TemplateDocumentType::Sf9)
                ->where('id', '!=', $latestRecord->id)
                ->update([
                    'is_finalized' => false,
                    'finalized_at' => null,
                    'finalized_by' => null,
                ]);

            $latestRecord->forceFill([
                'is_finalized' => true,
                'finalized_at' => now(),
                'finalized_by' => $adviser->id,
            ])->save();

            $this->auditLogger->log(
                $latestRecord,
                $adviser,
                ReportCardRecordAuditAction::Finalized,
                'Adviser finalized the SF9 record.',
            );

            return $latestRecord->fresh([
                'template',
                'generatedBy',
                'finalizedBy',
                'sectionRoster.learner',
                'auditLogs',
            ]);
        });
    }
}
