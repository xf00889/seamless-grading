<?php

namespace App\Services\TeacherGradeEntry;

use App\Enums\GradeSubmissionStatus;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\SectionRoster;
use App\Models\TeacherLoad;
use App\Services\LearnerMovement\LearnerMovementEligibilityService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GradeEntryPageDataBuilder
{
    public function __construct(
        private readonly GradingRuleResolver $gradingRuleResolver,
        private readonly LearnerMovementEligibilityService $eligibilityService,
    ) {}

    public function build(TeacherLoad $teacherLoad, GradingPeriod $gradingPeriod): array
    {
        if ($teacherLoad->school_year_id !== $gradingPeriod->school_year_id) {
            throw new NotFoundHttpException;
        }

        $teacherLoad->loadMissing([
            'schoolYear',
            'section.gradeLevel',
            'section.adviser',
            'subject',
        ]);

        $submission = GradeSubmission::query()
            ->with('quarterlyGrades')
            ->where('teacher_load_id', $teacherLoad->id)
            ->where('grading_period_id', $gradingPeriod->id)
            ->first();

        $gradingRules = $this->gradingRuleResolver->resolve();
        $quarterlyGrades = $submission?->quarterlyGrades->keyBy('section_roster_id') ?? collect();

        $rows = SectionRoster::query()
            ->with('learner')
            ->join('learners', 'learners.id', '=', 'section_rosters.learner_id')
            ->select('section_rosters.*')
            ->where('section_rosters.school_year_id', $teacherLoad->school_year_id)
            ->where('section_rosters.section_id', $teacherLoad->section_id)
            ->where('section_rosters.is_official', true)
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name')
            ->get()
            ->map(function (SectionRoster $sectionRoster) use ($gradingPeriod, $gradingRules, $quarterlyGrades): array {
                $currentGrade = $quarterlyGrades->get($sectionRoster->id);
                $eligibility = $this->eligibilityService->forGradingPeriod($sectionRoster, $gradingPeriod);
                $acceptsGrade = $eligibility['accepts_grade'];
                $gradeNote = $eligibility['reason'];

                if (! $acceptsGrade && $currentGrade?->grade !== null) {
                    $gradeNote .= ' Clear the persisted grade before saving or resubmitting this quarter.';
                }

                return [
                    'section_roster_id' => $sectionRoster->id,
                    'learner_name' => sprintf(
                        '%s, %s%s',
                        $sectionRoster->learner->last_name,
                        $sectionRoster->learner->first_name,
                        $sectionRoster->learner->middle_name !== null ? ' '.mb_substr($sectionRoster->learner->middle_name, 0, 1).'.' : '',
                    ),
                    'lrn' => $sectionRoster->learner->lrn,
                    'sex' => $sectionRoster->learner->sex->label(),
                    'enrollment_status' => [
                        'value' => $eligibility['status']['value'],
                        'label' => $eligibility['status']['label'],
                        'tone' => $eligibility['status']['tone'],
                    ],
                    'accepts_grade' => $acceptsGrade,
                    'grade_note' => $gradeNote,
                    'grade' => $currentGrade?->grade !== null
                        ? number_format((float) $currentGrade->grade, $gradingRules['decimal_places'], '.', '')
                        : '',
                    'remarks' => $currentGrade?->remarks,
                ];
            })
            ->values()
            ->all();

        $workflowStatus = $submission?->status ?? GradeSubmissionStatus::Draft;

        return [
            'load' => [
                'subject_name' => $teacherLoad->subject->name,
                'subject_code' => $teacherLoad->subject->code,
                'section_name' => $teacherLoad->section->name,
                'grade_level_name' => $teacherLoad->section->gradeLevel->name,
                'school_year_name' => $teacherLoad->schoolYear->name,
                'adviser_name' => $teacherLoad->section->adviser?->name ?? 'No adviser assigned',
                'is_active' => $teacherLoad->is_active,
            ],
            'grading_period' => [
                'quarter_label' => $gradingPeriod->quarter->label(),
                'is_open' => $gradingPeriod->is_open,
                'starts_on' => $gradingPeriod->starts_on?->format('M d, Y'),
                'ends_on' => $gradingPeriod->ends_on?->format('M d, Y'),
            ],
            'workflow' => [
                'status' => [
                    'value' => $workflowStatus->value,
                    'label' => $workflowStatus->label(),
                    'tone' => $workflowStatus->tone(),
                ],
                'is_editable' => $this->isEditable($teacherLoad, $submission),
                'block_message' => $this->blockMessage($teacherLoad, $submission),
                'adviser_remarks' => $submission?->adviser_remarks,
                'submitted_at' => $submission?->submitted_at?->format('M d, Y g:i A'),
                'returned_at' => $submission?->returned_at?->format('M d, Y g:i A'),
                'locked_at' => $submission?->locked_at?->format('M d, Y g:i A'),
                'updated_at' => $submission?->updated_at?->format('M d, Y g:i A'),
                'has_submission' => $submission !== null,
            ],
            'grading_rules' => $gradingRules,
            'rows' => $rows,
        ];
    }

    private function isEditable(TeacherLoad $teacherLoad, ?GradeSubmission $submission): bool
    {
        if (! $teacherLoad->is_active) {
            return false;
        }

        if ($submission === null) {
            return true;
        }

        if ($submission->locked_at !== null || $submission->status === GradeSubmissionStatus::Locked) {
            return false;
        }

        return in_array($submission->status, [GradeSubmissionStatus::Draft, GradeSubmissionStatus::Returned], true);
    }

    private function blockMessage(TeacherLoad $teacherLoad, ?GradeSubmission $submission): ?string
    {
        if (! $teacherLoad->is_active) {
            return 'This teaching load is inactive and cannot accept grade updates.';
        }

        if ($submission === null) {
            return null;
        }

        if ($submission->locked_at !== null || $submission->status === GradeSubmissionStatus::Locked) {
            return 'This submission is locked and cannot be edited until an administrator reopens it.';
        }

        if ($submission->status === GradeSubmissionStatus::Submitted) {
            return 'This submission has already been submitted and can only be edited if it is returned for correction.';
        }

        if ($submission->status === GradeSubmissionStatus::Approved) {
            return 'This submission has already been approved and is no longer editable.';
        }

        return null;
    }
}
