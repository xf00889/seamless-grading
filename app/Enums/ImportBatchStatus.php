<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case Uploaded = 'uploaded';
    case Validated = 'validated';
    case Confirmed = 'confirmed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Validated => 'Validated',
            self::Confirmed => 'Confirmed',
            self::Failed => 'Failed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Uploaded => 'sky',
            self::Validated => 'amber',
            self::Confirmed => 'emerald',
            self::Failed => 'rose',
        };
    }
}
