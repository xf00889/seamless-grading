<?php

namespace App\Models;

use Database\Factories\GradeLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeLevel extends Model
{
    use HasFactory;

    /** @use HasFactory<GradeLevelFactory> */
    protected $guarded = [];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }
}
