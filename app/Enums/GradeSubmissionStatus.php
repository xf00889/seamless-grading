<?php

namespace App\Enums;

enum GradeSubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Returned => 'Returned',
            self::Approved => 'Approved',
            self::Locked => 'Locked',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Submitted => 'sky',
            self::Returned => 'amber',
            self::Approved => 'emerald',
            self::Locked => 'rose',
        };
    }
}
