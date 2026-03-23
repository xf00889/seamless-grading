<?php

namespace App\Services\LearnerMovement;

use App\Enums\EnrollmentStatus;
use App\Enums\TemplateDocumentType;
use App\Models\GradingPeriod;
use App\Models\ReportCardRecord;
use App\Models\SectionRoster;
use Illuminate\Support\Carbon;

class LearnerMovementRecordInvalidationService
{
    public function invalidateForEligibilityChange(
        SectionRoster $sectionRoster,
        EnrollmentStatus $previousStatus,
        ?Carbon $previousEffectiveDate,
        EnrollmentStatus $currentStatus,
        ?Carbon $currentEffectiveDate,
    ): array {
        if (
            $previousStatus === $currentStatus
            && $this->comparableDate($previousEffectiveDate) === $this->comparableDate($currentEffectiveDate)
        ) {
            return [
                'sf9' => 0,
                'sf10' => 0,
            ];
        }

        $impactStartDate = collect([
            $this->impactStartDate($previousStatus, $previousEffectiveDate),
            $this->impactStartDate($currentStatus, $currentEffectiveDate),
        ])->filter()->sort()->first();

        $invalidatedSf9RecordCount = 0;

        if ($impactStartDate instanceof Carbon) {
            $impactedPeriodIds = GradingPeriod::query()
                ->where('school_year_id', $sectionRoster->school_year_id)
                ->where(function ($query) use ($impactStartDate): void {
                    $query
                        ->whereDate('ends_on', '>=', $impactStartDate->toDateString())
                        ->orWhere(function ($dateQuery) use ($impactStartDate): void {
                            $dateQuery
                                ->whereNull('ends_on')
                                ->whereDate('starts_on', '>=', $impactStartDate->toDateString());
                        });
                })
                ->pluck('id');

            if ($impactedPeriodIds->isNotEmpty()) {
                $sf9Query = ReportCardRecord::query()
                    ->where('section_roster_id', $sectionRoster->id)
                    ->whereIn('grading_period_id', $impactedPeriodIds)
                    ->where('document_type', TemplateDocumentType::Sf9)
                    ->where('is_finalized', true);

                $invalidatedSf9RecordCount = (clone $sf9Query)->count();

                $sf9Query->update([
                    'is_finalized' => false,
                    'finalized_at' => null,
                    'finalized_by' => null,
                ]);
            }
        }

        $sf10Query = ReportCardRecord::query()
            ->where('section_roster_id', $sectionRoster->id)
            ->where('school_year_id', $sectionRoster->school_year_id)
            ->where('document_type', TemplateDocumentType::Sf10)
            ->where('is_finalized', true);

        $invalidatedSf10RecordCount = (clone $sf10Query)->count();

        $sf10Query->update([
            'is_finalized' => false,
            'finalized_at' => null,
            'finalized_by' => null,
        ]);

        return [
            'sf9' => $invalidatedSf9RecordCount,
            'sf10' => $invalidatedSf10RecordCount,
        ];
    }

    private function impactStartDate(EnrollmentStatus $status, ?Carbon $effectiveDate): ?Carbon
    {
        return match ($status) {
            EnrollmentStatus::TransferredOut, EnrollmentStatus::Dropped => $effectiveDate?->copy(),
            default => null,
        };
    }

    private function comparableDate(?Carbon $date): ?string
    {
        return $date?->toDateString();
    }
}
