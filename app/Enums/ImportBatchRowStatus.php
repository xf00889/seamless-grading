<?php

namespace App\Enums;

enum ImportBatchRowStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Imported = 'imported';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Valid => 'Valid',
            self::Invalid => 'Invalid',
            self::Imported => 'Imported',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'sky',
            self::Valid => 'amber',
            self::Invalid => 'rose',
            self::Imported => 'emerald',
        };
    }
}
