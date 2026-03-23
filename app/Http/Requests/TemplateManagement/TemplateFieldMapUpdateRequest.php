<?php

namespace App\Http\Requests\TemplateManagement;

use App\Models\Template;
use App\Enums\TemplateMappingKind;
use App\Services\TemplateManagement\TemplateDefinitionRegistry;
use App\Services\TemplateManagement\TemplateMappingStatusEvaluator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TemplateFieldMapUpdateRequest extends FormRequest
{
    /**
     * @var array<int, string>
     */
    private array $invalidMappingConfigFields = [];

    public function authorize(): bool
    {
        $template = $this->route('template');

        return $template instanceof Template
            ? ($this->user()?->can('updateMappings', $template) ?? false)
            : false;
    }

    protected function prepareForValidation(): void
    {
        $mappings = collect($this->input('mappings', []))
            ->map(function ($mapping, string $fieldKey): array {
                $row = is_array($mapping) ? $mapping : [];
                $configJson = trim((string) ($row['mapping_config_json'] ?? ''));

                if ($configJson === '') {
                    $row['mapping_config'] = null;

                    return $row;
                }

                try {
                    $decoded = json_decode($configJson, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $this->invalidMappingConfigFields[] = $fieldKey;
                    $row['mapping_config'] = null;

                    return $row;
                }

                if (! is_array($decoded)) {
                    $this->invalidMappingConfigFields[] = $fieldKey;
                    $row['mapping_config'] = null;

                    return $row;
                }

                $row['mapping_config'] = $decoded;

                return $row;
            })
            ->all();

        $this->merge([
            'mappings' => $mappings,
        ]);
    }

    public function rules(): array
    {
        return [
            'mappings' => ['required', 'array'],
            'mappings.*.mapping_kind' => ['nullable', Rule::enum(TemplateMappingKind::class)],
            'mappings.*.target_cell' => ['nullable', 'string', 'max:255'],
            'mappings.*.sheet_name' => ['nullable', 'string', 'max:255'],
            'mappings.*.mapping_config_json' => ['nullable', 'string', 'max:5000'],
            'mappings.*.mapping_config' => ['nullable', 'array'],
            'mappings.*.default_value' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $template = $this->route('template');

                if (! $template instanceof Template) {
                    return;
                }

                $definitions = app(TemplateDefinitionRegistry::class)->definitionsFor($template->document_type);
                $allowedFieldKeys = collect($definitions)->pluck('field_key')->all();
                $mappings = $this->input('mappings', []);
                $evaluator = app(TemplateMappingStatusEvaluator::class);

                foreach ($this->invalidMappingConfigFields as $fieldKey) {
                    $validator->errors()->add(
                        "mappings.$fieldKey.mapping_config_json",
                        'Provide a valid JSON object for the mapping config.',
                    );
                }

                foreach (array_keys($mappings) as $fieldKey) {
                    if (! in_array($fieldKey, $allowedFieldKeys, true)) {
                        $validator->errors()->add("mappings.$fieldKey", 'This mapping field is not supported for the selected template type.');
                    }
                }

                foreach ($allowedFieldKeys as $fieldKey) {
                    $definition = app(TemplateDefinitionRegistry::class)->fieldDefinition($template->document_type, $fieldKey);
                    $mappingKind = data_get($mappings, $fieldKey.'.mapping_kind')
                        ? TemplateMappingKind::from((string) data_get($mappings, $fieldKey.'.mapping_kind'))
                        : $evaluator->defaultMappingKind($definition);
                    $targetCell = trim((string) data_get($mappings, $fieldKey.'.target_cell', ''));

                    if ($targetCell !== '' && ! in_array($mappingKind, [
                        TemplateMappingKind::SplitFieldGroup,
                    ], true) && ! $evaluator->isValidTargetCell($targetCell)) {
                        $validator->errors()->add(
                            "mappings.$fieldKey.target_cell",
                            'Use a valid spreadsheet cell reference or named range.',
                        );
                    }

                    if (! in_array($mappingKind, $evaluator->allowedMappingKinds($definition), true)) {
                        $validator->errors()->add(
                            "mappings.$fieldKey.mapping_kind",
                            'This mapping type is not supported for the selected template field.',
                        );
                    }
                }
            },
        ];
    }
}
