<?php

namespace App\Models;

use App\Enums\ImportBatchRowStatus;
use Database\Factories\ImportBatchRowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatchRow extends Model
{
    use HasFactory;

    /** @use HasFactory<ImportBatchRowFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'normalized_data' => 'array',
            'errors' => 'array',
            'status' => ImportBatchRowStatus::class,
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
