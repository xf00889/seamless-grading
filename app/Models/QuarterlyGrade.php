<?php

namespace App\Models;

use Database\Factories\QuarterlyGradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuarterlyGrade extends Model
{
    use HasFactory;

    /** @use HasFactory<QuarterlyGradeFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
        ];
    }

    public function gradeSubmission(): BelongsTo
    {
        return $this->belongsTo(GradeSubmission::class);
    }

    public function sectionRoster(): BelongsTo
    {
        return $this->belongsTo(SectionRoster::class);
    }

    public function gradeChangeLogs(): HasMany
    {
        return $this->hasMany(GradeChangeLog::class);
    }
}
