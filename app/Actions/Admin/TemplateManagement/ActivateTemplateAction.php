<?php

namespace App\Actions\Admin\TemplateManagement;

use App\Enums\TemplateAuditAction;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateManagement\TemplateAuditLogger;
use App\Services\TemplateManagement\TemplateMappingStatusEvaluator;
use App\Services\TemplateManagement\TemplateScopeKeyFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateTemplateAction
{
    public function __construct(
        private readonly TemplateMappingStatusEvaluator $statusEvaluator,
        private readonly TemplateScopeKeyFactory $scopeKeyFactory,
        private readonly TemplateAuditLogger $auditLogger,
    ) {}

    public function handle(User $actor, Template $template): Template
    {
        return DB::transaction(function () use ($actor, $template): Template {
            $lockedTemplate = Template::query()
                ->with('fieldMaps')
                ->lockForUpdate()
                ->findOrFail($template->id);

            $mappingStatus = $this->statusEvaluator->evaluate($lockedTemplate);

            if ($mappingStatus['status']['value'] !== 'complete') {
                throw ValidationException::withMessages(
                    $this->validationMessages(
                        $mappingStatus['errors'],
                        $mappingStatus['error_keys'] ?? [],
                    ),
                );
            }

            $siblings = Template::query()
                ->where('document_type', $lockedTemplate->document_type)
                ->where('scope_key', $lockedTemplate->scope_key)
                ->where('is_active', true)
                ->whereKeyNot($lockedTemplate->id)
                ->lockForUpdate()
                ->get();

            foreach ($siblings as $sibling) {
                $sibling->forceFill([
                    'is_active' => false,
                    'active_scope_key' => null,
                    'deactivated_at' => now(),
                ])->save();

                $this->auditLogger->log(
                    $sibling,
                    $actor,
                    TemplateAuditAction::Deactivated,
                    'Template deactivated because a newer version was activated for the same template type and scope.',
                    ['superseded_by_template_id' => $lockedTemplate->id],
                );
            }

            $lockedTemplate->forceFill([
                'is_active' => true,
                'active_scope_key' => $this->scopeKeyFactory->activeScopeKey(
                    $lockedTemplate->document_type,
                    $lockedTemplate->grade_level_id,
                ),
                'activated_at' => now(),
                'deactivated_at' => null,
            ])->save();

            $this->auditLogger->log(
                $lockedTemplate,
                $actor,
                TemplateAuditAction::Activated,
                'Template activated.',
            );

            return $lockedTemplate->fresh(['gradeLevel', 'fieldMaps']);
        });
    }

    private function validationMessages(array $errors, array $errorKeys): array
    {
        $messages = [];

        foreach ($errors as $fieldKey => $message) {
            $messages[$errorKeys[$fieldKey] ?? "mappings.$fieldKey.target_cell"] = $message;
        }

        if ($messages === []) {
            $messages['record'] = 'Activation is blocked until all required template mappings are complete and valid.';
        }

        return $messages;
    }
}
