<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use Database\Factories\ApprovalLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLog extends Model
{
    use HasFactory;

    /** @use HasFactory<ApprovalLogFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action' => ApprovalAction::class,
            'metadata' => 'array',
        ];
    }

    public function gradeSubmission(): BelongsTo
    {
        return $this->belongsTo(GradeSubmission::class);
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
