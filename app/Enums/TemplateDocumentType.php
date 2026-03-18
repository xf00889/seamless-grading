<?php

namespace App\Enums;

enum TemplateDocumentType: string
{
    case GradingSheet = 'grading_sheet';
    case Sf9 = 'sf9';
    case Sf10 = 'sf10';
}
