<?php

namespace App\Services\TemplateManagement;

use App\Enums\TemplateAuditAction;
use App\Models\Template;
use App\Models\User;

class TemplateAuditLogger
{
    public function log(
        Template $template,
        ?User $actor,
        TemplateAuditAction $action,
        ?string $remarks = null,
        array $metadata = [],
    ): void {
        $template->auditLogs()->create([
            'acted_by' => $actor?->id,
            'action' => $action,
            'remarks' => $remarks,
            'metadata' => array_merge([
                'entity_type' => Template::class,
                'entity_id' => $template->id,
                'document_type' => $template->document_type->value,
                'scope_key' => $template->scope_key,
                'version' => $template->version,
            ], $metadata),
        ]);
    }
}
