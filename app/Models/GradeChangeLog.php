<?php

namespace App\Models;

use Database\Factories\GradeChangeLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeChangeLog extends Model
{
    use HasFactory;

    /** @use HasFactory<GradeChangeLogFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'previous_grade' => 'decimal:2',
            'new_grade' => 'decimal:2',
        ];
    }

    public function quarterlyGrade(): BelongsTo
    {
        return $this->belongsTo(QuarterlyGrade::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
