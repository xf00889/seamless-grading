<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case TransferredOut = 'transferred_out';
}
