<?php

namespace App\Services\TeacherGradingSheet;

use App\Enums\GradingSheetExportAuditAction;
use App\Models\GradingSheetExport;
use App\Models\User;

class GradingSheetExportAuditLogger
{
    public function log(
        GradingSheetExport $gradingSheetExport,
        ?User $actor,
        GradingSheetExportAuditAction $action,
        ?string $remarks = null,
        array $metadata = [],
    ): void {
        $gradingSheetExport->auditLogs()->create([
            'acted_by' => $actor?->id,
            'action' => $action,
            'remarks' => $remarks,
            'metadata' => array_merge([
                'entity_type' => GradingSheetExport::class,
                'entity_id' => $gradingSheetExport->id,
                'teacher_load_id' => $gradingSheetExport->teacher_load_id,
                'grading_period_id' => $gradingSheetExport->grading_period_id,
                'template_id' => $gradingSheetExport->template_id,
                'template_version' => $gradingSheetExport->template_version,
                'export_version' => $gradingSheetExport->version,
            ], $metadata),
        ]);
    }
}
