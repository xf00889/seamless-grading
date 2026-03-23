<?php

namespace App\Enums;

enum TemplateDocumentType: string
{
    case GradingSheet = 'grading_sheet';
    case Sf9 = 'sf9';
    case Sf10 = 'sf10';

    public function label(): string
    {
        return match ($this) {
            self::GradingSheet => 'Grading Sheet',
            self::Sf9 => 'SF9',
            self::Sf10 => 'SF10',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::GradingSheet => 'sky',
            self::Sf9 => 'emerald',
            self::Sf10 => 'amber',
        };
    }
}
