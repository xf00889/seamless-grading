<?php

namespace App\Services\AdviserYearEnd;

use App\Enums\LearnerStatusAuditAction;
use App\Models\SectionRoster;
use App\Models\User;

class LearnerStatusAuditLogger
{
    public function log(
        SectionRoster $sectionRoster,
        ?User $actor,
        LearnerStatusAuditAction $action,
        ?string $remarks = null,
        array $metadata = [],
    ): void {
        $sectionRoster->learnerStatusAuditLogs()->create([
            'acted_by' => $actor?->id,
            'action' => $action,
            'remarks' => $remarks,
            'metadata' => array_merge([
                'entity_type' => SectionRoster::class,
                'entity_id' => $sectionRoster->id,
                'section_id' => $sectionRoster->section_id,
                'school_year_id' => $sectionRoster->school_year_id,
                'learner_id' => $sectionRoster->learner_id,
                'year_end_status' => $sectionRoster->year_end_status?->value,
                'enrollment_status' => $sectionRoster->enrollment_status->value,
                'effective_date' => $sectionRoster->withdrawn_on?->toDateString(),
                'movement_reason' => $sectionRoster->movement_reason,
            ], $metadata),
        ]);
    }
}
