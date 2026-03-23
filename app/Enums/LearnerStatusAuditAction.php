<?php

namespace App\Enums;

enum LearnerStatusAuditAction: string
{
    case YearEndStatusUpdated = 'year_end_status_updated';
    case TransferredOutMarked = 'transferred_out_marked';
    case DroppedMarked = 'dropped_marked';
    case MovementCleared = 'movement_cleared';
    case MovementCorrected = 'movement_corrected';

    public function label(): string
    {
        return match ($this) {
            self::YearEndStatusUpdated => 'Year-End Status Updated',
            self::TransferredOutMarked => 'Transferred Out Marked',
            self::DroppedMarked => 'Dropped Marked',
            self::MovementCleared => 'Movement Cleared',
            self::MovementCorrected => 'Movement Corrected',
        };
    }
}
