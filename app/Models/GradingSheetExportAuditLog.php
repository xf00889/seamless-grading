<?php

namespace App\Models;

use App\Enums\GradingSheetExportAuditAction;
use Database\Factories\GradingSheetExportAuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingSheetExportAuditLog extends Model
{
    use HasFactory;

    /** @use HasFactory<GradingSheetExportAuditLogFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action' => GradingSheetExportAuditAction::class,
            'metadata' => 'array',
        ];
    }

    public function gradingSheetExport(): BelongsTo
    {
        return $this->belongsTo(GradingSheetExport::class);
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
