<?php

namespace App\Services\TemplateManagement;

use App\Enums\TemplateDocumentType;
use InvalidArgumentException;

class TemplateDefinitionRegistry
{
    public function definitionsFor(TemplateDocumentType $documentType): array
    {
        return config('templates.definitions.'.$documentType->value, []);
    }

    public function requiredFieldKeysFor(TemplateDocumentType $documentType): array
    {
        return collect($this->definitionsFor($documentType))
            ->filter(fn (array $definition): bool => (bool) ($definition['required'] ?? false))
            ->pluck('field_key')
            ->all();
    }

    public function allowedExtensionsFor(TemplateDocumentType $documentType): array
    {
        return config('templates.allowed_extensions.'.$documentType->value, []);
    }

    public function fieldDefinition(TemplateDocumentType $documentType, string $fieldKey): array
    {
        return collect($this->definitionsFor($documentType))
            ->firstWhere('field_key', $fieldKey)
            ?? throw new InvalidArgumentException('Unknown template field key.');
    }
}
