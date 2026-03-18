<?php

namespace App\Enums;

enum ApprovalAction: string
{
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Approved = 'approved';
    case Locked = 'locked';
    case Reopened = 'reopened';
}
