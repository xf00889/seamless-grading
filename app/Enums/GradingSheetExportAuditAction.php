<?php

namespace App\Enums;

enum GradingSheetExportAuditAction: string
{
    case Exported = 'exported';

    public function label(): string
    {
        return match ($this) {
            self::Exported => 'Exported',
        };
    }
}
