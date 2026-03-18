<?php

namespace App\Enums;

enum GradingQuarter: int
{
    case First = 1;
    case Second = 2;
    case Third = 3;
    case Fourth = 4;

    public function label(): string
    {
        return 'Q'.$this->value;
    }
}
