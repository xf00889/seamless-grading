<?php

namespace App\Enums;

enum ImportBatchRowStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Imported = 'imported';
}
