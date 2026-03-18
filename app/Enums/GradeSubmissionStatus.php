<?php

namespace App\Enums;

enum GradeSubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';
    case Locked = 'locked';
}
