<?php

namespace App\Services\LearnerMovement;

use App\Enums\EnrollmentStatus;
use App\Models\GradingPeriod;
use App\Models\SectionRoster;
use Illuminate\Support\Carbon;

class LearnerMovementEligibilityService
{
    public function currentStatus(SectionRoster $sectionRoster): EnrollmentStatus
    {
        if ($sectionRoster->enrollment_status !== EnrollmentStatus::Active) {
            return $sectionRoster->enrollment_status;
        }

        $learnerStatus = $sectionRoster->learner?->enrollment_status;

        return $learnerStatus instanceof EnrollmentStatus
            ? $learnerStatus
            : EnrollmentStatus::Active;
    }

    public function effectiveDate(SectionRoster $sectionRoster): ?Carbon
    {
        if ($sectionRoster->withdrawn_on !== null) {
            return $sectionRoster->withdrawn_on->copy();
        }

        return $sectionRoster->learner?->transfer_effective_date?->copy();
    }

    public function summary(SectionRoster $sectionRoster): array
    {
        $status = $this->currentStatus($sectionRoster);
        $effectiveDate = $this->effectiveDate($sectionRoster);

        return [
            'status' => [
                'value' => $status->value,
                'label' => $status->label(),
                'tone' => $status->tone(),
            ],
            'effective_date' => $effectiveDate?->toDateString(),
            'effective_date_label' => $effectiveDate?->format('M d, Y'),
            'is_exception' => $status !== EnrollmentStatus::Active,
            'reason' => $sectionRoster->movement_reason,
        ];
    }

    public function forGradingPeriod(SectionRoster $sectionRoster, GradingPeriod $gradingPeriod): array
    {
        $summary = $this->summary($sectionRoster);
        $status = $this->currentStatus($sectionRoster);
        $effectiveDate = $this->effectiveDate($sectionRoster);
        $periodBoundary = $gradingPeriod->ends_on?->copy() ?? $gradingPeriod->starts_on?->copy();

        if ($status === EnrollmentStatus::Active) {
            return $summary + [
                'accepts_grade' => true,
                'requires_grade' => true,
                'reason' => 'This learner is eligible for grading in '.$gradingPeriod->quarter->label().'.',
            ];
        }

        if ($status === EnrollmentStatus::Inactive) {
            return $summary + [
                'accepts_grade' => false,
                'requires_grade' => false,
                'reason' => 'This learner is inactive and cannot receive quarterly grades.',
            ];
        }

        if ($effectiveDate === null) {
            return $summary + [
                'accepts_grade' => false,
                'requires_grade' => false,
                'reason' => 'This learner movement exception is missing an effective date and cannot be treated as grade-eligible.',
            ];
        }

        if ($periodBoundary === null) {
            return $summary + [
                'accepts_grade' => false,
                'requires_grade' => false,
                'reason' => 'This grading period does not have enough date information to evaluate learner movement eligibility safely.',
            ];
        }

        if ($effectiveDate->lte($periodBoundary)) {
            $verb = $status === EnrollmentStatus::TransferredOut ? 'transferred out' : 'dropped';

            return $summary + [
                'accepts_grade' => false,
                'requires_grade' => false,
                'reason' => sprintf(
                    'This learner was %s effective %s and is not grade-eligible for %s or later periods.',
                    $verb,
                    $effectiveDate->format('M d, Y'),
                    $gradingPeriod->quarter->label(),
                ),
            ];
        }

        return $summary + [
            'accepts_grade' => true,
            'requires_grade' => true,
            'reason' => sprintf(
                'This learner remains grade-eligible for %s because the %s takes effect on %s.',
                $gradingPeriod->quarter->label(),
                $status === EnrollmentStatus::TransferredOut ? 'transfer-out' : 'dropout',
                $effectiveDate->format('M d, Y'),
            ),
        ];
    }
}
