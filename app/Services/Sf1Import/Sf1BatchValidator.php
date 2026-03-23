<?php

namespace App\Services\Sf1Import;

use App\Enums\ImportBatchRowStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\LearnerSex;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Learner;
use App\Models\SectionRoster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class Sf1BatchValidator
{
    public function revalidate(ImportBatch $importBatch): void
    {
        $importBatch->loadMissing('section.schoolYear');

        $rows = $importBatch->rows()->orderBy('row_number')->get();

        if ($rows->isEmpty()) {
            $importBatch->forceFill([
                'status' => ImportBatchStatus::Failed,
                'total_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
            ])->save();

            return;
        }

        $preparedRows = $rows->map(fn (ImportBatchRow $row): array => [
            'row' => $row,
            'data' => $this->normalizeRowData($row->normalized_data ?: $row->payload),
        ]);

        $duplicateLrns = $preparedRows
            ->pluck('data.lrn')
            ->filter()
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1);

        $duplicateIdentities = $preparedRows
            ->pluck('data.identity_key')
            ->filter()
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1);

        $learnersByLrn = Learner::query()
            ->whereIn('lrn', $preparedRows->pluck('data.lrn')->filter()->unique()->all())
            ->get()
            ->keyBy('lrn');

        $learnersByIdentity = $this->learnersByIdentity($preparedRows);
        $manualLearners = Learner::query()
            ->whereIn('id', $rows->pluck('learner_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $analyzedRows = $preparedRows->map(function (array $preparedRow) use (
            $duplicateIdentities,
            $duplicateLrns,
            $learnersByIdentity,
            $learnersByLrn,
            $manualLearners,
        ): array {
            return $this->analyzeRow(
                $preparedRow['row'],
                $preparedRow['data'],
                $duplicateLrns,
                $duplicateIdentities,
                $learnersByLrn,
                $learnersByIdentity,
                $manualLearners,
            );
        });

        $rostersByLearner = SectionRoster::query()
            ->with('section')
            ->where('school_year_id', $importBatch->section->school_year_id)
            ->whereIn(
                'learner_id',
                $analyzedRows->pluck('matched_learner.id')->filter()->unique()->all(),
            )
            ->get()
            ->keyBy('learner_id');

        foreach ($analyzedRows as $analysis) {
            $row = $analysis['row'];
            $errors = $analysis['errors'];
            $matchedRoster = $analysis['matched_learner'] !== null
                ? $rostersByLearner->get($analysis['matched_learner']->id)
                : null;

            if (
                $matchedRoster !== null
                && $matchedRoster->section_id !== $importBatch->section_id
            ) {
                $errors[] = $this->error(
                    'duplicate_roster_assignment',
                    'This learner is already assigned to another section in the same school year.',
                );
            }

            $row->forceFill([
                'learner_id' => $analysis['matched_learner']?->id,
                'normalized_data' => [
                    ...$analysis['data'],
                    'candidate_learner_ids' => $analysis['candidate_learners']->pluck('id')->values()->all(),
                    'matched_learner_id' => $analysis['matched_learner']?->id,
                    'matched_roster_id' => $matchedRoster?->id,
                    'matched_roster_section_name' => $matchedRoster?->section?->name,
                ],
                'errors' => $errors === [] ? null : $errors,
                'status' => $errors === [] ? ImportBatchRowStatus::Valid : ImportBatchRowStatus::Invalid,
            ])->save();
        }

        $freshRows = $importBatch->rows()->get();

        $importBatch->forceFill([
            'status' => ImportBatchStatus::Validated,
            'total_rows' => $freshRows->count(),
            'valid_rows' => $freshRows->filter(fn ($row) => $row->status === ImportBatchRowStatus::Valid)->count(),
            'invalid_rows' => $freshRows->filter(fn ($row) => $row->status === ImportBatchRowStatus::Invalid)->count(),
        ])->save();
    }

    public function normalizeRowData(array $data): array
    {
        $normalized = [
            'lrn' => $this->normalizeLrn($data['lrn'] ?? null),
            'last_name' => $this->normalizeName($data['last_name'] ?? null),
            'first_name' => $this->normalizeName($data['first_name'] ?? null),
            'middle_name' => $this->normalizeName($data['middle_name'] ?? null),
            'suffix' => $this->normalizeSuffix($data['suffix'] ?? null),
            'sex' => $this->normalizeSex($data['sex'] ?? null)?->value,
            'birth_date' => $this->normalizeBirthDate($data['birth_date'] ?? null),
        ];

        $normalized['identity_key'] = $this->identityKey($normalized);

        return $normalized;
    }

    public function candidateLearnersForRow(ImportBatchRow $importBatchRow): Collection
    {
        return $this->candidateLearnersForData(
            $this->normalizeRowData($importBatchRow->normalized_data ?: $importBatchRow->payload),
        );
    }

    private function analyzeRow(
        ImportBatchRow $row,
        array $data,
        Collection $duplicateLrns,
        Collection $duplicateIdentities,
        Collection $learnersByLrn,
        Collection $learnersByIdentity,
        Collection $manualLearners,
    ): array {
        $errors = [];

        if ($data['lrn'] === null) {
            $errors[] = $this->error('missing_lrn', 'LRN is required.');
        } elseif (strlen($data['lrn']) !== 12) {
            $errors[] = $this->error('invalid_lrn', 'LRN must contain exactly 12 digits.');
        }

        if ($data['last_name'] === null) {
            $errors[] = $this->error('missing_last_name', 'Last name is required.');
        }

        if ($data['first_name'] === null) {
            $errors[] = $this->error('missing_first_name', 'First name is required.');
        }

        if ($data['sex'] === null) {
            $errors[] = $this->error('missing_sex', 'Sex is required and must be Male or Female.');
        }

        if ($data['birth_date'] === null) {
            $errors[] = $this->error('missing_birth_date', 'Birth date is required.');
        }

        if ($data['lrn'] !== null && $duplicateLrns->has($data['lrn'])) {
            $errors[] = $this->error(
                'duplicate_lrn_in_batch',
                'This LRN appears more than once in the uploaded file.',
            );
        }

        if ($data['identity_key'] !== null && $duplicateIdentities->has($data['identity_key'])) {
            $errors[] = $this->error(
                'duplicate_learner_in_batch',
                'This learner appears more than once in the uploaded file.',
            );
        }

        $candidateLearners = $data['identity_key'] !== null
            ? $learnersByIdentity->get($data['identity_key'], collect())
            : collect();

        if ($candidateLearners->isEmpty() && $data['identity_key'] !== null) {
            $candidateLearners = $this->candidateLearnersForData($data);
        }

        $matchedLearner = null;
        $manualLearner = $row->learner_id !== null ? $manualLearners->get($row->learner_id) : null;

        if ($manualLearner !== null) {
            if ($data['lrn'] !== $manualLearner->lrn) {
                $errors[] = $this->error(
                    'selected_learner_lrn_mismatch',
                    'The selected learner does not match the provided LRN.',
                );
            } else {
                $matchedLearner = $manualLearner;
            }
        } elseif ($data['lrn'] !== null && $learnersByLrn->has($data['lrn'])) {
            $matchedLearner = $learnersByLrn->get($data['lrn']);

            if (! $this->matchesLearnerIdentity($data, $matchedLearner)) {
                $errors[] = $this->error(
                    'existing_lrn_conflict',
                    'This LRN already belongs to another learner record with different details.',
                );
            }
        } elseif ($candidateLearners->count() === 1) {
            $errors[] = $this->error(
                'possible_duplicate_learner',
                'A learner record with matching identity already exists. Review the row and link the correct learner if appropriate.',
            );
        } elseif ($candidateLearners->count() > 1) {
            $errors[] = $this->error(
                'multiple_duplicate_learners',
                'Multiple learner records match this row. Resolve the row before confirming the import.',
            );
        }

        return [
            'row' => $row,
            'data' => $data,
            'errors' => $errors,
            'matched_learner' => $matchedLearner,
            'candidate_learners' => $candidateLearners,
        ];
    }

    private function learnersByIdentity(Collection $preparedRows): Collection
    {
        $lastNames = $preparedRows->pluck('data.last_name')->filter()->unique()->all();
        $firstNames = $preparedRows->pluck('data.first_name')->filter()->unique()->all();
        $birthDates = $preparedRows->pluck('data.birth_date')->filter()->unique()->all();

        if ($lastNames === [] || $firstNames === [] || $birthDates === []) {
            return collect();
        }

        return Learner::query()
            ->whereIn('last_name', $lastNames)
            ->whereIn('first_name', $firstNames)
            ->whereIn('birth_date', $birthDates)
            ->get()
            ->groupBy(fn (Learner $learner): ?string => $this->identityKey([
                'last_name' => $learner->last_name,
                'first_name' => $learner->first_name,
                'middle_name' => $learner->middle_name,
                'suffix' => $learner->suffix,
                'sex' => $learner->sex->value,
                'birth_date' => $learner->birth_date?->toDateString(),
            ]));
    }

    private function candidateLearnersForData(array $data): Collection
    {
        if ($data['identity_key'] === null) {
            return collect();
        }

        return Learner::query()
            ->where('last_name', $data['last_name'])
            ->where('first_name', $data['first_name'])
            ->get()
            ->filter(fn (Learner $learner): bool => $this->identityKey([
                'last_name' => $learner->last_name,
                'first_name' => $learner->first_name,
                'middle_name' => $learner->middle_name,
                'suffix' => $learner->suffix,
                'sex' => $learner->sex->value,
                'birth_date' => $learner->birth_date?->toDateString(),
            ]) === $data['identity_key'])
            ->values();
    }

    private function matchesLearnerIdentity(array $data, Learner $learner): bool
    {
        return $this->identityKey($data) === $this->identityKey([
            'last_name' => $learner->last_name,
            'first_name' => $learner->first_name,
            'middle_name' => $learner->middle_name,
            'suffix' => $learner->suffix,
            'sex' => $learner->sex->value,
            'birth_date' => $learner->birth_date?->toDateString(),
        ]);
    }

    private function identityKey(array $data): ?string
    {
        if (
            $data['last_name'] === null
            || $data['first_name'] === null
            || $data['sex'] === null
            || $data['birth_date'] === null
        ) {
            return null;
        }

        return collect([
            Str::upper($data['last_name']),
            Str::upper($data['first_name']),
            Str::upper($data['middle_name'] ?? ''),
            Str::upper($data['suffix'] ?? ''),
            Str::upper($data['sex']),
            $data['birth_date'],
        ])->implode('|');
    }

    private function normalizeLrn(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    private function normalizeName(mixed $value): ?string
    {
        $string = Str::of((string) $value)->squish()->trim();

        if ($string->isEmpty()) {
            return null;
        }

        return $string->title()->value();
    }

    private function normalizeSuffix(mixed $value): ?string
    {
        $string = Str::of((string) $value)->squish()->trim();

        if ($string->isEmpty()) {
            return null;
        }

        return Str::upper($string->value());
    }

    private function normalizeSex(mixed $value): ?LearnerSex
    {
        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'm', 'male' => LearnerSex::Male,
            'f', 'female' => LearnerSex::Female,
            default => null,
        };
    }

    private function normalizeBirthDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function error(string $code, string $message): array
    {
        return [
            'code' => $code,
            'message' => $message,
        ];
    }
}
