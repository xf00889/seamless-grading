<?php

namespace App\Models;

use Database\Factories\TeacherLoadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherLoad extends Model
{
    use HasFactory;

    /** @use HasFactory<TeacherLoadFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function gradeSubmissions(): HasMany
    {
        return $this->hasMany(GradeSubmission::class);
    }

    public function gradingSheetExports(): HasMany
    {
        return $this->hasMany(GradingSheetExport::class);
    }
}
