<?php

namespace App\Actions\Admin\TemplateManagement;

use App\Enums\TemplateAuditAction;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateManagement\TemplateAuditLogger;
use App\Services\TemplateManagement\TemplateDefinitionRegistry;
use App\Services\TemplateManagement\TemplateMappingStatusEvaluator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTemplateFieldMapsAction
{
    public function __construct(
        private readonly TemplateDefinitionRegistry $definitionRegistry,
        private readonly TemplateMappingStatusEvaluator $statusEvaluator,
        private readonly TemplateAuditLogger $auditLogger,
    ) {}

    public function handle(User $actor, Template $template, array $mappings): Template
    {
        return DB::transaction(function () use ($actor, $template, $mappings): Template {
            $lockedTemplate = Template::query()
                ->with('fieldMaps')
                ->lockForUpdate()
                ->findOrFail($template->id);

            $fieldDefinitions = $this->definitionRegistry->definitionsFor($lockedTemplate->document_type);
            $allowedFieldKeys = collect($fieldDefinitions)->pluck('field_key')->all();

            $lockedTemplate->fieldMaps()
                ->whereNotIn('field_key', $allowedFieldKeys)
                ->delete();

            foreach ($fieldDefinitions as $definition) {
                $fieldKey = $definition['field_key'];

                $lockedTemplate->fieldMaps()->updateOrCreate(
                    ['field_key' => $fieldKey],
                    [
                        'mapping_kind' => data_get($mappings, $fieldKey.'.mapping_kind')
                            ?? ($definition['default_mapping_kind'] ?? null),
                        'target_cell' => $this->nullableString(data_get($mappings, $fieldKey.'.target_cell')),
                        'sheet_name' => $this->nullableString(data_get($mappings, $fieldKey.'.sheet_name')),
                        'mapping_config' => $this->nullableArray(data_get($mappings, $fieldKey.'.mapping_config')),
                        'default_value' => $this->nullableString(data_get($mappings, $fieldKey.'.default_value')),
                        'is_required' => (bool) ($definition['required'] ?? false),
                    ],
                );
            }

            $lockedTemplate->load('fieldMaps');
            $mappingStatus = $this->statusEvaluator->evaluate($lockedTemplate);

            if ($lockedTemplate->is_active && $mappingStatus['status']['value'] !== 'complete') {
                throw ValidationException::withMessages(
                    $this->validationMessages(
                        $mappingStatus['errors'],
                        $mappingStatus['error_keys'] ?? [],
                    ),
                );
            }

            $this->auditLogger->log(
                $lockedTemplate,
                $actor,
                TemplateAuditAction::MappingsUpdated,
                'Template mappings updated.',
                ['mapping_status' => $mappingStatus['status']['value']],
            );

            return $lockedTemplate->fresh(['gradeLevel', 'fieldMaps']);
        });
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = trim((string) ($value ?? ''));

        return $stringValue !== '' ? $stringValue : null;
    }

    private function nullableArray(mixed $value): ?array
    {
        return is_array($value) && $value !== [] ? $value : null;
    }

    private function validationMessages(array $errors, array $errorKeys): array
    {
        $messages = [];

        foreach ($errors as $fieldKey => $message) {
            $messages[$errorKeys[$fieldKey] ?? "mappings.$fieldKey.target_cell"] = $message;
        }

        if ($messages === []) {
            $messages['record'] = 'The active template must keep all required mappings complete and valid.';
        }

        return $messages;
    }
}
