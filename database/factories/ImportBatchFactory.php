<?php

namespace Database\Factories;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_id' => Section::factory(),
            'imported_by' => User::factory(),
            'status' => ImportBatchStatus::Uploaded,
            'source_file_name' => fake()->lexify('sf1-????').'.xlsx',
            'source_disk' => 'local',
            'source_path' => 'imports/'.fake()->uuid().'.xlsx',
            'total_rows' => fake()->numberBetween(10, 40),
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'confirmed_at' => null,
        ];
    }
}
