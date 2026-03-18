<?php

namespace App\Models;

use App\Enums\GradingQuarter;
use Database\Factories\GradingPeriodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingPeriod extends Model
{
    use HasFactory;

    /** @use HasFactory<GradingPeriodFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quarter' => GradingQuarter::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_open' => 'boolean',
        ];
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function gradeSubmissions(): HasMany
    {
        return $this->hasMany(GradeSubmission::class);
    }

    public function gradingSheetExports(): HasMany
    {
        return $this->hasMany(GradingSheetExport::class);
    }

    public function reportCardRecords(): HasMany
    {
        return $this->hasMany(ReportCardRecord::class);
    }
}
