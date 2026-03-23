<?php

namespace App\Actions\Adviser\YearEnd;

use App\Enums\ReportCardRecordAuditAction;
use App\Models\ReportCardRecord;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\User;
use App\Services\AdviserSf9\ReportCardRecordAuditLogger;
use App\Services\AdviserYearEnd\AdviserYearEndContextResolver;
use App\Services\AdviserYearEnd\Sf10PreviewDataBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeSf10RecordAction
{
    public function __construct(
        private readonly AdviserYearEndContextResolver $contextResolver,
        private readonly Sf10PreviewDataBuilder $previewDataBuilder,
        private readonly ReportCardRecordAuditLogger $auditLogger,
    ) {}

    public function handle(
        User $adviser,
        Section $section,
        SectionRoster $sectionRoster,
        ReportCardRecord $reportCardRecord,
    ): ReportCardRecord {
        $this->contextResolver->assertReportCardRecordScope($section, $sectionRoster, $reportCardRecord);

        $previewData = $this->previewDataBuilder->build($section, $sectionRoster);
        $draftReview = $previewData['draft_review'];

        if (! $previewData['finalize_ready']) {
            throw ValidationException::withMessages([
                'record' => $previewData['finalization_blockers'][0] ?? 'The SF10 draft cannot be finalized yet.',
            ]);
        }

        if ($draftReview === null) {
            throw ValidationException::withMessages([
                'record' => 'Generate an SF10 draft export version before finalizing this learner record.',
            ]);
        }

        if ((int) $draftReview['id'] !== (int) $reportCardRecord->id) {
            throw ValidationException::withMessages([
                'record' => 'Only the latest current SF10 draft version can be finalized.',
            ]);
        }

        return DB::transaction(function () use (
            $adviser,
            $reportCardRecord,
            $previewData,
        ): ReportCardRecord {
            $lockedRecord = ReportCardRecord::query()
                ->lockForUpdate()
                ->findOrFail($reportCardRecord->id);

            if ($lockedRecord->is_finalized) {
                throw ValidationException::withMessages([
                    'record' => 'This SF10 version is already finalized.',
                ]);
            }

            $latestRecord = ReportCardRecord::query()
                ->where('section_roster_id', $lockedRecord->section_roster_id)
                ->where('grading_period_id', $lockedRecord->grading_period_id)
                ->where('document_type', $lockedRecord->document_type)
                ->orderByDesc('record_version')
                ->lockForUpdate()
                ->first();

            if ($latestRecord === null || (int) $latestRecord->id !== (int) $lockedRecord->id) {
                throw ValidationException::withMessages([
                    'record' => 'A newer SF10 draft version exists. Reload the page before finalizing.',
                ]);
            }

            $currentTemplate = $previewData['template']['model'] ?? null;
            $currentSourceHash = (string) $previewData['source_hash'];

            if (
                $currentTemplate === null
                || (int) $lockedRecord->template_id !== (int) $currentTemplate->id
                || (int) $lockedRecord->template_version !== (int) $currentTemplate->version
                || (string) data_get($lockedRecord->payload, 'source_hash') !== $currentSourceHash
            ) {
                throw ValidationException::withMessages([
                    'record' => 'The selected SF10 draft is stale relative to the current approved data or active template. Generate a new draft version before finalizing.',
                ]);
            }

            ReportCardRecord::query()
                ->where('section_roster_id', $lockedRecord->section_roster_id)
                ->where('grading_period_id', $lockedRecord->grading_period_id)
                ->where('document_type', $lockedRecord->document_type)
                ->where('id', '!=', $lockedRecord->id)
                ->update([
                    'is_finalized' => false,
                    'finalized_at' => null,
                    'finalized_by' => null,
                ]);

            $lockedRecord->forceFill([
                'is_finalized' => true,
                'finalized_at' => now(),
                'finalized_by' => $adviser->id,
            ])->save();

            $this->auditLogger->log(
                $lockedRecord,
                $adviser,
                ReportCardRecordAuditAction::Finalized,
                'Adviser finalized the SF10 record for registrar handoff.',
            );

            return $lockedRecord->fresh([
                'template',
                'generatedBy',
                'finalizedBy',
                'sectionRoster.learner',
                'auditLogs',
            ]);
        });
    }
}
