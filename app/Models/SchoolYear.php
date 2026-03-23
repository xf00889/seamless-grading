<?php

namespace App\Models;

use Database\Factories\SchoolYearFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolYear extends Model
{
    use HasFactory;

    /** @use HasFactory<SchoolYearFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function gradingPeriods(): HasMany
    {
        return $this->hasMany(GradingPeriod::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function teacherLoads(): HasMany
    {
        return $this->hasMany(TeacherLoad::class);
    }

    public function sectionRosters(): HasMany
    {
        return $this->hasMany(SectionRoster::class);
    }

    public function reportCardRecords(): HasMany
    {
        return $this->hasMany(ReportCardRecord::class);
    }
}
