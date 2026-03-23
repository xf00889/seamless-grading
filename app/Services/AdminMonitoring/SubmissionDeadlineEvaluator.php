<?php

namespace App\Services\AdminMonitoring;

class SubmissionDeadlineEvaluator
{
    public function isLate(mixed $submittedAt, mixed $deadline, mixed $referenceTime = null): bool
    {
        if ($deadline === null) {
            return false;
        }

        $referenceTime ??= now();

        if ($submittedAt === null) {
            return $referenceTime->greaterThan($deadline);
        }

        return $submittedAt->greaterThan($deadline);
    }

    public function lateReason(mixed $submittedAt, mixed $deadline, mixed $referenceTime = null): ?string
    {
        if (! $this->isLate($submittedAt, $deadline, $referenceTime)) {
            return null;
        }

        if ($submittedAt === null) {
            return 'No formal submission exists after the grading-period deadline.';
        }

        return 'The latest teacher submission happened after the grading-period deadline.';
    }
}
