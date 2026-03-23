<?php

namespace App\Actions\Admin\TemplateManagement;

use App\Enums\TemplateAuditAction;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateManagement\TemplateAuditLogger;
use Illuminate\Support\Facades\DB;

class DeactivateTemplateAction
{
    public function __construct(
        private readonly TemplateAuditLogger $auditLogger,
    ) {}

    public function handle(User $actor, Template $template): Template
    {
        return DB::transaction(function () use ($actor, $template): Template {
            $lockedTemplate = Template::query()
                ->lockForUpdate()
                ->findOrFail($template->id);

            if (! $lockedTemplate->is_active) {
                return $lockedTemplate;
            }

            $lockedTemplate->forceFill([
                'is_active' => false,
                'active_scope_key' => null,
                'deactivated_at' => now(),
            ])->save();

            $this->auditLogger->log(
                $lockedTemplate,
                $actor,
                TemplateAuditAction::Deactivated,
                'Template deactivated.',
            );

            return $lockedTemplate->fresh(['gradeLevel', 'fieldMaps']);
        });
    }
}
