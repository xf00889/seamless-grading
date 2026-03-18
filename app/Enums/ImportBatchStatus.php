<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case Uploaded = 'uploaded';
    case Validated = 'validated';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
}
