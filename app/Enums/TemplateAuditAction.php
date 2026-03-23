<?php

namespace App\Enums;

enum TemplateAuditAction: string
{
    case Uploaded = 'uploaded';
    case MappingsUpdated = 'mappings_updated';
    case Activated = 'activated';
    case Deactivated = 'deactivated';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::MappingsUpdated => 'Mappings Updated',
            self::Activated => 'Activated',
            self::Deactivated => 'Deactivated',
        };
    }
}
