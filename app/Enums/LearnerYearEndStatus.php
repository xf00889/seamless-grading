<?php

namespace App\Enums;

enum LearnerYearEndStatus: string
{
    case Promoted = 'promoted';
    case Retained = 'retained';
    case TransferredOut = 'transferred_out';
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::Promoted => 'Promoted',
            self::Retained => 'Retained',
            self::TransferredOut => 'Transferred out',
            self::Dropped => 'Dropped',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Promoted => 'emerald',
            self::Retained => 'amber',
            self::TransferredOut => 'rose',
            self::Dropped => 'slate',
        };
    }

    public function requiresReason(): bool
    {
        return match ($this) {
            self::TransferredOut, self::Dropped => true,
            self::Promoted, self::Retained => false,
        };
    }

    public function actionTakenLabel(): string
    {
        return match ($this) {
            self::Promoted => 'Promoted',
            self::Retained => 'Retained',
            self::TransferredOut => 'Transferred Out',
            self::Dropped => 'Dropped',
        };
    }
}
