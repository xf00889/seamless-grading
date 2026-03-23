<?php

namespace App\Actions\Admin\Sf1Imports;

use App\Models\ImportBatch;
use App\Services\Sf1Import\Sf1BatchValidator;

class ValidateSf1ImportBatchAction
{
    public function __construct(
        private readonly Sf1BatchValidator $validator,
    ) {}

    public function handle(ImportBatch $importBatch): void
    {
        $this->validator->revalidate($importBatch);
    }
}
