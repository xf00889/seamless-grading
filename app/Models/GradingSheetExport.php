<?php

namespace App\Models;

use Database\Factories\GradingSheetExportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingSheetExport extends Model
{
    use HasFactory;

    /** @use HasFactory<GradingSheetExportFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'exported_at' => 'datetime',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function exportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
