<?php

namespace App\Actions\Admin\Sf1Imports;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use Illuminate\Validation\ValidationException;

class ResolveSf1ImportRowAction
{
    public function __construct(
        private readonly ValidateSf1ImportBatchAction $validateAction,
    ) {}

    public function handle(ImportBatch $importBatch, ImportBatchRow $importBatchRow, array $attributes): void
    {
        if ($importBatch->status === ImportBatchStatus::Confirmed) {
            throw ValidationException::withMessages([
                'record' => 'Confirmed batches can no longer be edited.',
            ]);
        }

        $importBatchRow->forceFill([
            'learner_id' => $attributes['learner_id'] ?? null,
            'normalized_data' => [
                'lrn' => $attributes['lrn'],
                'last_name' => $attributes['last_name'],
                'first_name' => $attributes['first_name'],
                'middle_name' => $attributes['middle_name'] ?? null,
                'suffix' => $attributes['suffix'] ?? null,
                'sex' => $attributes['sex'],
                'birth_date' => $attributes['birth_date'],
            ],
        ])->save();

        $this->validateAction->handle($importBatch);
    }
}
