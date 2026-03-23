<?php

namespace App\Models;

use App\Enums\ReportCardRecordAuditAction;
use Database\Factories\ReportCardRecordAuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportCardRecordAuditLog extends Model
{
    use HasFactory;

    /** @use HasFactory<ReportCardRecordAuditLogFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action' => ReportCardRecordAuditAction::class,
            'metadata' => 'array',
        ];
    }

    public function reportCardRecord(): BelongsTo
    {
        return $this->belongsTo(ReportCardRecord::class);
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
