<?php

namespace App\Actions\LearnerMovement;

use App\Enums\EnrollmentStatus;
use App\Enums\LearnerStatusAuditAction;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\User;
use App\Services\AdviserYearEnd\LearnerStatusAuditLogger;
use App\Services\LearnerMovement\LearnerMovementEligibilityService;
use App\Services\LearnerMovement\LearnerMovementRecordInvalidationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateLearnerMovementExceptionAction
{
    public function __construct(
        private readonly LearnerMovementEligibilityService $eligibilityService,
        private readonly LearnerMovementRecordInvalidationService $recordInvalidationService,
        private readonly LearnerStatusAuditLogger $auditLogger,
    ) {}

    public function handle(
        User $actor,
        Section $section,
        SectionRoster $sectionRoster,
        array $validated,
    ): SectionRoster {
        $this->ensureSectionRosterScope($section, $sectionRoster);

        return DB::transaction(function () use ($actor, $section, $sectionRoster, $validated): SectionRoster {
            $lockedRoster = SectionRoster::query()
                ->with([
                    'learner',
                    'movementRecordedBy:id,name',
                    'yearEndStatusSetBy:id,name',
                ])
                ->lockForUpdate()
                ->findOrFail($sectionRoster->id);

            $this->ensureSectionRosterScope($section, $lockedRoster);

            $previousStatus = $this->eligibilityService->currentStatus($lockedRoster);
            $previousEffectiveDate = $this->eligibilityService->effectiveDate($lockedRoster);
            $previousReason = $lockedRoster->movement_reason;

            $status = EnrollmentStatus::from($validated['status']);
            $effectiveDate = $this->normalizedEffectiveDate($status, $validated['effective_date'] ?? null);
            $reason = $this->normalizedReason($validated['reason'] ?? null);

            $this->ensureTransitionIsValid($section, $lockedRoster, $status, $effectiveDate, $reason);

            if (
                $previousStatus === $status
                && $this->comparableDate($previousEffectiveDate) === $this->comparableDate($effectiveDate)
                && $previousReason === $reason
            ) {
                return $lockedRoster;
            }

            $lockedRoster->forceFill([
                'enrollment_status' => $status,
                'withdrawn_on' => $status === EnrollmentStatus::Active ? null : $effectiveDate,
                'movement_reason' => $status === EnrollmentStatus::Active ? null : $reason,
                'movement_recorded_at' => $status === EnrollmentStatus::Active ? null : now(),
                'movement_recorded_by' => $status === EnrollmentStatus::Active ? null : $actor->id,
            ])->save();

            if ($section->schoolYear?->is_active) {
                $lockedRoster->learner->forceFill([
                    'enrollment_status' => $status,
                    'transfer_effective_date' => $status === EnrollmentStatus::TransferredOut ? $effectiveDate : null,
                ])->save();
            }

            $invalidatedRecords = $this->recordInvalidationService->invalidateForEligibilityChange(
                $lockedRoster,
                $previousStatus,
                $previousEffectiveDate,
                $status,
                $effectiveDate,
            );

            $auditAction = $this->auditAction(
                $previousStatus,
                $previousEffectiveDate,
                $previousReason,
                $status,
                $effectiveDate,
                $reason,
            );

            $this->auditLogger->log(
                $lockedRoster,
                $actor,
                $auditAction,
                $this->auditRemarks($auditAction, $status, $effectiveDate, $reason),
                [
                    'previous_enrollment_status' => $previousStatus->value,
                    'previous_effective_date' => $this->comparableDate($previousEffectiveDate),
                    'previous_reason' => $previousReason,
                    'current_enrollment_status' => $status->value,
                    'effective_date' => $this->comparableDate($effectiveDate),
                    'movement_reason' => $reason,
                    'invalidated_sf9_record_count' => $invalidatedRecords['sf9'],
                    'invalidated_sf10_record_count' => $invalidatedRecords['sf10'],
                ],
            );

            return $lockedRoster->fresh([
                'learner',
                'movementRecordedBy:id,name',
                'yearEndStatusSetBy:id,name',
            ]);
        });
    }

    private function ensureSectionRosterScope(Section $section, SectionRoster $sectionRoster): void
    {
        if (
            $sectionRoster->section_id !== $section->id
            || $sectionRoster->school_year_id !== $section->school_year_id
            || ! $sectionRoster->is_official
        ) {
            throw ValidationException::withMessages([
                'record' => 'The selected learner movement record does not belong to the official roster for this section and school year.',
            ]);
        }
    }

    private function ensureTransitionIsValid(
        Section $section,
        SectionRoster $sectionRoster,
        EnrollmentStatus $status,
        ?Carbon $effectiveDate,
        ?string $reason,
    ): void {
        if (! in_array($status, [
            EnrollmentStatus::Active,
            EnrollmentStatus::TransferredOut,
            EnrollmentStatus::Dropped,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only active, transferred out, and dropped states are supported in this workflow.',
            ]);
        }

        if ($status === EnrollmentStatus::TransferredOut && $effectiveDate === null) {
            throw ValidationException::withMessages([
                'effective_date' => 'An effective transfer-out date is required.',
            ]);
        }

        if ($status === EnrollmentStatus::Dropped && blank($reason)) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when marking a learner as dropped.',
            ]);
        }

        if ($effectiveDate !== null) {
            if ($effectiveDate->lt($section->schoolYear->starts_on) || $effectiveDate->gt($section->schoolYear->ends_on)) {
                throw ValidationException::withMessages([
                    'effective_date' => 'The effective date must fall within the selected school year.',
                ]);
            }

            if ($sectionRoster->enrolled_on !== null && $effectiveDate->lt($sectionRoster->enrolled_on)) {
                throw ValidationException::withMessages([
                    'effective_date' => 'The effective date cannot be earlier than the learner roster enrollment date.',
                ]);
            }
        }
    }

    private function normalizedEffectiveDate(EnrollmentStatus $status, mixed $value): ?Carbon
    {
        if ($status === EnrollmentStatus::Active) {
            return null;
        }

        if (filled($value)) {
            return Carbon::parse((string) $value)->startOfDay();
        }

        return $status === EnrollmentStatus::Dropped ? now()->startOfDay() : null;
    }

    private function normalizedReason(mixed $value): ?string
    {
        $reason = trim((string) $value);

        return $reason !== '' ? $reason : null;
    }

    private function comparableDate(?Carbon $date): ?string
    {
        return $date?->toDateString();
    }

    private function auditAction(
        EnrollmentStatus $previousStatus,
        ?Carbon $previousEffectiveDate,
        ?string $previousReason,
        EnrollmentStatus $status,
        ?Carbon $effectiveDate,
        ?string $reason,
    ): LearnerStatusAuditAction {
        if ($status === EnrollmentStatus::Active) {
            return LearnerStatusAuditAction::MovementCleared;
        }

        if (
            $previousStatus === $status
            && (
                $this->comparableDate($previousEffectiveDate) !== $this->comparableDate($effectiveDate)
                || $previousReason !== $reason
            )
        ) {
            return LearnerStatusAuditAction::MovementCorrected;
        }

        return match ($status) {
            EnrollmentStatus::TransferredOut => LearnerStatusAuditAction::TransferredOutMarked,
            EnrollmentStatus::Dropped => LearnerStatusAuditAction::DroppedMarked,
            default => LearnerStatusAuditAction::MovementCorrected,
        };
    }

    private function auditRemarks(
        LearnerStatusAuditAction $auditAction,
        EnrollmentStatus $status,
        ?Carbon $effectiveDate,
        ?string $reason,
    ): string {
        return match ($auditAction) {
            LearnerStatusAuditAction::TransferredOutMarked => sprintf(
                'Learner marked as transferred out effective %s.%s',
                $effectiveDate?->format('M d, Y') ?? 'unknown date',
                filled($reason) ? ' '.$reason : '',
            ),
            LearnerStatusAuditAction::DroppedMarked => filled($reason)
                ? 'Learner marked as dropped. '.$reason
                : 'Learner marked as dropped.',
            LearnerStatusAuditAction::MovementCleared => 'Learner movement exception cleared and roster grading eligibility restored.',
            LearnerStatusAuditAction::MovementCorrected => sprintf(
                'Learner movement exception corrected to %s%s.',
                $status->label(),
                $effectiveDate !== null ? ' effective '.$effectiveDate->format('M d, Y') : '',
            ),
            default => 'Learner movement exception updated.',
        };
    }
}
