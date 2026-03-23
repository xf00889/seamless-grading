<?php

namespace App\Enums;

enum ReportCardRecordAuditAction: string
{
    case Exported = 'exported';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Exported => 'Exported',
            self::Finalized => 'Finalized',
        };
    }
}
