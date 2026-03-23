<?php

namespace App\Services\TemplateManagement;

use App\Enums\TemplateDocumentType;

class TemplateScopeKeyFactory
{
    public function scopeKey(?int $gradeLevelId): string
    {
        return $gradeLevelId === null
            ? 'global'
            : 'grade-level:'.$gradeLevelId;
    }

    public function activeScopeKey(TemplateDocumentType $documentType, ?int $gradeLevelId): string
    {
        return $documentType->value.':'.$this->scopeKey($gradeLevelId);
    }
}
