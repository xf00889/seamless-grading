<?php

namespace App\Models;

use App\Enums\LearnerStatusAuditAction;
use Database\Factories\LearnerStatusAuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerStatusAuditLog extends Model
{
    use HasFactory;

    /** @use HasFactory<LearnerStatusAuditLogFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action' => LearnerStatusAuditAction::class,
            'metadata' => 'array',
        ];
    }

    public function sectionRoster(): BelongsTo
    {
        return $this->belongsTo(SectionRoster::class);
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
