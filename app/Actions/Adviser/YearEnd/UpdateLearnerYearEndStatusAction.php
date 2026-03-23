<?php

namespace App\Actions\Adviser\YearEnd;

use App\Enums\EnrollmentStatus;
use App\Enums\LearnerStatusAuditAction;
use App\Enums\LearnerYearEndStatus;
use App\Enums\TemplateDocumentType;
use App\Models\ReportCardRecord;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\User;
use App\Services\AdviserYearEnd\AdviserYearEndContextResolver;
use App\Services\AdviserYearEnd\LearnerStatusAuditLogger;
use App\Services\AdviserYearEnd\YearEndGradeReadinessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateLearnerYearEndStatusAction
{
    public function __construct(
        private readonly AdviserYearEndContextResolver $contextResolver,
        private readonly YearEndGradeReadinessService $readinessService,
        private readonly LearnerStatusAuditLogger $auditLogger,
    ) {}

    public function handle(
        User $adviser,
        Section $section,
        SectionRoster $sectionRoster,
        array $validated,
    ): SectionRoster {
        $this->contextResolver->assertSectionRosterScope($section, $sectionRoster);
        $sectionRoster->loadMissing('learner');

        $status = LearnerYearEndStatus::from($validated['status']);
        $reason = trim((string) ($validated['reason'] ?? ''));
        $readiness = $this->readinessService->detail($section, $sectionRoster);
        $normalizedReason = $reason !== '' ? $reason : null;

        $this->ensureTransitionIsAllowed($sectionRoster, $status, $reason, $readiness);

        return DB::transaction(function () use ($adviser, $sectionRoster, $status, $reason, $normalizedReason): SectionRoster {
            $previousStatus = $sectionRoster->year_end_status?->value;
            $previousReason = $sectionRoster->year_end_status_reason;
            $statusChanged = $previousStatus !== $status->value || $previousReason !== $normalizedReason;

            if (! $statusChanged) {
                return $sectionRoster->fresh(['learner', 'yearEndStatusSetBy']);
            }

            $sectionRoster->forceFill([
                'year_end_status' => $status,
                'year_end_status_reason' => $normalizedReason,
                'year_end_status_set_at' => now(),
                'year_end_status_set_by' => $adviser->id,
            ])->save();

            $invalidatedSf10RecordCount = ReportCardRecord::query()
                ->where('section_roster_id', $sectionRoster->id)
                ->where('school_year_id', $sectionRoster->school_year_id)
                ->where('document_type', TemplateDocumentType::Sf10)
                ->where('is_finalized', true)
                ->update([
                    'is_finalized' => false,
                    'finalized_at' => null,
                    'finalized_by' => null,
                ]);

            $this->auditLogger->log(
                $sectionRoster,
                $adviser,
                LearnerStatusAuditAction::YearEndStatusUpdated,
                $reason !== '' ? $reason : 'Adviser updated the learner year-end status.',
                [
                    'previous_year_end_status' => $previousStatus,
                    'previous_reason' => $previousReason,
                    'current_year_end_status' => $status->value,
                    'invalidated_sf10_record_count' => $invalidatedSf10RecordCount,
                ],
            );

            return $sectionRoster->fresh(['learner', 'yearEndStatusSetBy']);
        });
    }

    private function ensureTransitionIsAllowed(
        SectionRoster $sectionRoster,
        LearnerYearEndStatus $status,
        string $reason,
        array $readiness,
    ): void {
        if ($status->requiresReason() && $reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when marking a learner as transferred out or dropped.',
            ]);
        }

        if (in_array($status, [LearnerYearEndStatus::Promoted, LearnerYearEndStatus::Retained], true)) {
            if (
                in_array($sectionRoster->enrollment_status, [EnrollmentStatus::TransferredOut, EnrollmentStatus::Dropped], true)
                || in_array($sectionRoster->learner->enrollment_status, [EnrollmentStatus::TransferredOut, EnrollmentStatus::Dropped], true)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'A learner with an active transfer-out or dropout exception cannot be placed on the normal promotion or retention flow.',
                ]);
            }

            if (! $readiness['full_year_ready']) {
                throw ValidationException::withMessages([
                    'status' => $readiness['full_year_blockers'][0] ?? 'Approved final year-end grade data is incomplete for this learner.',
                ]);
            }
        }
    }
}
