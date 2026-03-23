<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\LearnerYearEndStatus;
use Database\Factories\SectionRosterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectionRoster extends Model
{
    use HasFactory;

    /** @use HasFactory<SectionRosterFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enrollment_status' => EnrollmentStatus::class,
            'enrolled_on' => 'date',
            'withdrawn_on' => 'date',
            'movement_recorded_at' => 'datetime',
            'year_end_status' => LearnerYearEndStatus::class,
            'year_end_status_set_at' => 'datetime',
            'is_official' => 'boolean',
        ];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function yearEndStatusSetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'year_end_status_set_by');
    }

    public function movementRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'movement_recorded_by');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function quarterlyGrades(): HasMany
    {
        return $this->hasMany(QuarterlyGrade::class);
    }

    public function reportCardRecords(): HasMany
    {
        return $this->hasMany(ReportCardRecord::class);
    }

    public function learnerStatusAuditLogs(): HasMany
    {
        return $this->hasMany(LearnerStatusAuditLog::class);
    }
}
