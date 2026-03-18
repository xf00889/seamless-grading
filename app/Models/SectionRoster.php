<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
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
}
