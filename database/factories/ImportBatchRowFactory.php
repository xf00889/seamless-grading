<?php

namespace Database\Factories;

use App\Enums\ImportBatchRowStatus;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Learner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatchRow>
 */
class ImportBatchRowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'import_batch_id' => ImportBatch::factory(),
            'learner_id' => Learner::factory(),
            'row_number' => fake()->numberBetween(1, 60),
            'payload' => [
                'lrn' => fake()->numerify('############'),
                'last_name' => fake()->lastName(),
                'first_name' => fake()->firstName(),
            ],
            'normalized_data' => null,
            'errors' => null,
            'status' => ImportBatchRowStatus::Pending,
        ];
    }
}
