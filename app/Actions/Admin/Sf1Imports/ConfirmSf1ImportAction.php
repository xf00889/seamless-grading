<?php

namespace App\Actions\Admin\Sf1Imports;

use App\Enums\EnrollmentStatus;
use App\Enums\ImportBatchRowStatus;
use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\Learner;
use App\Models\SectionRoster;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSf1ImportAction
{
    public function __construct(
        private readonly ValidateSf1ImportBatchAction $validateAction,
    ) {}

    public function handle(User $actor, ImportBatch $importBatch): void
    {
        if ($importBatch->status === ImportBatchStatus::Confirmed) {
            throw ValidationException::withMessages([
                'record' => 'This import batch has already been confirmed.',
            ]);
        }

        $this->validateAction->handle($importBatch);
        $importBatch->refresh();

        if (
            $importBatch->invalid_rows > 0
            || $importBatch->rows()->where('status', '!=', ImportBatchRowStatus::Valid->value)->exists()
        ) {
            throw ValidationException::withMessages([
                'record' => 'Resolve all row-level validation and duplicate issues before confirming this import.',
            ]);
        }

        $importBatch->loadMissing('section.schoolYear', 'rows');

        DB::transaction(function () use ($actor, $importBatch): void {
            foreach ($importBatch->rows as $row) {
                $data = $row->normalized_data ?? [];

                $learner = $row->learner_id !== null
                    ? Learner::query()->findOrFail($row->learner_id)
                    : Learner::query()->firstOrCreate(
                        ['lrn' => $data['lrn']],
                        [
                            'last_name' => $data['last_name'],
                            'first_name' => $data['first_name'],
                            'middle_name' => $data['middle_name'] ?? null,
                            'suffix' => $data['suffix'] ?? null,
                            'sex' => $data['sex'],
                            'birth_date' => $data['birth_date'],
                            'enrollment_status' => EnrollmentStatus::Active,
                        ],
                    );

                $learner->update([
                    'lrn' => $data['lrn'],
                    'last_name' => $data['last_name'],
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'suffix' => $data['suffix'] ?? null,
                    'sex' => $data['sex'],
                    'birth_date' => $data['birth_date'],
                ]);

                $sectionRoster = SectionRoster::query()
                    ->where('school_year_id', $importBatch->section->school_year_id)
                    ->where('learner_id', $learner->id)
                    ->first();

                if ($sectionRoster !== null && $sectionRoster->section_id !== $importBatch->section_id) {
                    throw ValidationException::withMessages([
                        'record' => 'A learner in this batch is already assigned to another section in the same school year.',
                    ]);
                }

                SectionRoster::query()->updateOrCreate(
                    [
                        'school_year_id' => $importBatch->section->school_year_id,
                        'learner_id' => $learner->id,
                    ],
                    [
                        'section_id' => $importBatch->section_id,
                        'import_batch_id' => $importBatch->id,
                        'enrollment_status' => EnrollmentStatus::Active,
                        'enrolled_on' => $sectionRoster?->enrolled_on ?? $importBatch->section->schoolYear->starts_on,
                        'withdrawn_on' => null,
                        'is_official' => true,
                    ],
                );

                $row->forceFill([
                    'learner_id' => $learner->id,
                    'errors' => null,
                    'status' => ImportBatchRowStatus::Imported,
                ])->save();
            }

            $importBatch->forceFill([
                'status' => ImportBatchStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by' => $actor->id,
                'valid_rows' => $importBatch->rows()->count(),
                'invalid_rows' => 0,
            ])->save();
        });
    }
}
