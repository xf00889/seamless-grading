<?php

namespace App\Enums;

enum ApprovalAction: string
{
    case DraftSaved = 'draft_saved';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';
    case Locked = 'locked';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::DraftSaved => 'Draft Saved',
            self::Submitted => 'Submitted',
            self::Returned => 'Returned',
            self::Approved => 'Approved',
            self::Locked => 'Locked',
            self::Reopened => 'Reopened',
        };
    }
}
