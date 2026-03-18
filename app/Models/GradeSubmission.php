<?php

namespace App\Models;

use App\Enums\GradeSubmissionStatus;
use Database\Factories\GradeSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeSubmission extends Model
{
    use HasFactory;

    /** @use HasFactory<GradeSubmissionFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => GradeSubmissionStatus::class,
            'submitted_at' => 'datetime',
            'returned_at' => 'datetime',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    public function teacherLoad(): BelongsTo
    {
        return $this->belongsTo(TeacherLoad::class);
    }

    public function gradingPeriod(): BelongsTo
    {
        return $this->belongsTo(GradingPeriod::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function quarterlyGrades(): HasMany
    {
        return $this->hasMany(QuarterlyGrade::class);
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class);
    }
}
