<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\LearnerSex;
use Database\Factories\LearnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Learner extends Model
{
    use HasFactory;

    /** @use HasFactory<LearnerFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sex' => LearnerSex::class,
            'birth_date' => 'date',
            'enrollment_status' => EnrollmentStatus::class,
            'transfer_effective_date' => 'date',
        ];
    }

    public function sectionRosters(): HasMany
    {
        return $this->hasMany(SectionRoster::class);
    }
}
