<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case TransferredOut = 'transferred_out';
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::TransferredOut => 'Transferred out',
            self::Dropped => 'Dropped',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'emerald',
            self::Inactive => 'slate',
            self::TransferredOut => 'amber',
            self::Dropped => 'rose',
        };
    }
}
