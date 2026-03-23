<?php

namespace App\Models;

use App\Enums\TemplateAuditAction;
use Database\Factories\TemplateAuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateAuditLog extends Model
{
    use HasFactory;

    /** @use HasFactory<TemplateAuditLogFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action' => TemplateAuditAction::class,
            'metadata' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
