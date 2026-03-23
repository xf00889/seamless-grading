<?php

namespace App\Services\TeacherGradeEntry;

use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\SectionRoster;
use App\Models\TeacherLoad;
use App\Services\LearnerMovement\LearnerMovementEligibilityService;
use Illuminate\Validation\ValidationException;

class GradeEntryPayloadValidator
{
    public function __construct(
        private readonly GradingRuleResolver $gradingRuleResolver,
        private readonly LearnerMovementEligibilityService $eligibilityService,
    ) {}

    public function validate(
        TeacherLoad $teacherLoad,
        GradingPeriod $gradingPeriod,
        array $grades,
        bool $strictCompleteness,
        ?GradeSubmission $existingSubmission = null,
    ): array {
        if ($teacherLoad->school_year_id !== $gradingPeriod->school_year_id) {
            throw ValidationException::withMessages([
                'form.record' => 'The selected grading period does not belong to this teaching load.',
            ]);
        }

        if (! $teacherLoad->is_active) {
            throw ValidationException::withMessages([
                'form.record' => 'This teaching load is inactive and cannot accept grade updates.',
            ]);
        }

        $officialRosters = SectionRoster::query()
            ->with('learner')
            ->where('school_year_id', $teacherLoad->school_year_id)
            ->where('section_id', $teacherLoad->section_id)
            ->where('is_official', true)
            ->get()
            ->keyBy('id');

        if ($officialRosters->isEmpty()) {
            throw ValidationException::withMessages([
                'form.record' => 'No official roster learners are available for this teaching load.',
            ]);
        }

        if (
            $existingSubmission !== null
            && $existingSubmission->quarterlyGrades()
                ->whereNotIn('section_roster_id', $officialRosters->keys())
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'form.record' => 'The saved grade rows no longer match the official roster for this load and grading period.',
            ]);
        }

        $unknownRosterIds = collect(array_keys($grades))
            ->map(fn ($key) => (int) $key)
            ->reject(fn (int $rosterId): bool => $officialRosters->has($rosterId))
            ->values();

        if ($unknownRosterIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'form.record' => 'One or more submitted grade rows do not belong to the official roster for this load.',
            ]);
        }

        $gradingRules = $this->gradingRuleResolver->resolve();
        $errors = [];
        $gradeRows = [];

        foreach ($officialRosters as $sectionRoster) {
            $property = 'form.grades.'.$sectionRoster->id.'.grade';
            $rawGrade = $this->normalizeGrade(data_get($grades, $sectionRoster->id.'.grade'));
            $normalizedGrade = null;
            $eligibility = $this->eligibilityService->forGradingPeriod($sectionRoster, $gradingPeriod);

            if ($rawGrade !== null && ! is_numeric($rawGrade)) {
                $errors[$property] = 'Grades must be numeric values.';

                continue;
            }

            if ($rawGrade !== null) {
                if (
                    preg_match('/^\d{1,3}(?:\.\d{1,'.$gradingRules['decimal_places'].'})?$/', $rawGrade) !== 1
                ) {
                    $errors[$property] = 'Grades may contain up to '.$gradingRules['decimal_places'].' decimal places only.';

                    continue;
                }

                $normalizedGrade = (float) $rawGrade;

                if ($normalizedGrade < $gradingRules['minimum'] || $normalizedGrade > $gradingRules['maximum']) {
                    $errors[$property] = 'Grades must be between '.$gradingRules['minimum'].' and '.$gradingRules['maximum'].'.';

                    continue;
                }

                if (! $eligibility['accepts_grade']) {
                    $errors[$property] = $eligibility['reason'];

                    continue;
                }
            }

            if (
                $normalizedGrade === null
                && $eligibility['requires_grade']
                && $this->gradeIsRequired($gradingRules, $strictCompleteness)
            ) {
                $errors[$property] = $strictCompleteness
                    ? 'A grade is required for every learner who is still grade-eligible for this period before submission.'
                    : 'A grade is required for every learner who is still grade-eligible for this period before this draft can be saved.';

                continue;
            }

            $gradeRows[] = [
                'section_roster_id' => $sectionRoster->id,
                'grade' => $normalizedGrade,
                'remarks' => $normalizedGrade === null
                    ? null
                    : ($normalizedGrade >= $gradingRules['passing'] ? 'Passed' : 'Failed'),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'grading_rules' => $gradingRules,
            'grade_rows' => $gradeRows,
        ];
    }

    private function normalizeGrade(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        if (! is_numeric($normalized)) {
            return $normalized;
        }

        return $normalized;
    }

    private function gradeIsRequired(array $gradingRules, bool $strictCompleteness): bool
    {
        if ($strictCompleteness) {
            return ! $gradingRules['allow_blank_active_learners'];
        }

        return ! $gradingRules['allow_blank_in_drafts'];
    }
}
