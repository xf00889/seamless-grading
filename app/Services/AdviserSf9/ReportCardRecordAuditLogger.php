<?php

namespace App\Services\AdviserSf9;

use App\Enums\ReportCardRecordAuditAction;
use App\Enums\TemplateDocumentType;
use App\Models\ReportCardRecord;
use App\Models\User;

class ReportCardRecordAuditLogger
{
    public function log(
        ReportCardRecord $reportCardRecord,
        ?User $actor,
        ReportCardRecordAuditAction $action,
        ?string $remarks = null,
        array $metadata = [],
    ): void {
        $reportCardRecord->auditLogs()->create([
            'acted_by' => $actor?->id,
            'action' => $action,
            'remarks' => $remarks,
            'metadata' => array_merge([
                'entity_type' => ReportCardRecord::class,
                'entity_id' => $reportCardRecord->id,
                'section_id' => $reportCardRecord->section_id,
                'school_year_id' => $reportCardRecord->school_year_id,
                'learner_id' => $reportCardRecord->learner_id,
                'grading_period_id' => $reportCardRecord->grading_period_id,
                'document_type' => $reportCardRecord->document_type?->value ?? TemplateDocumentType::Sf9->value,
                'template_id' => $reportCardRecord->template_id,
                'template_version' => $reportCardRecord->template_version,
                'record_version' => $reportCardRecord->record_version,
            ], $metadata),
        ]);
    }
}
