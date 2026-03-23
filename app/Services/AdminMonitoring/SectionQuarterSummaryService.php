<?php

namespace App\Services\AdminMonitoring;

use App\Enums\GradeSubmissionStatus;
use App\Enums\TemplateDocumentType;
use App\Models\GradingPeriod;
use App\Models\ReportCardRecord;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\TeacherLoad;
use App\Services\LearnerMovement\LearnerMovementEligibilityService;
use Illuminate\Support\Collection;

class SectionQuarterSummaryService
{
    public function __construct(
        private readonly SubmissionDeadlineEvaluator $deadlineEvaluator,
        private readonly LearnerMovementEligibilityService $eligibilityService,
    ) {}

    public function summaryForSection(Section $section, GradingPeriod $gradingPeriod): array
    {
        return $this->summariesForSections(collect([$section]), $gradingPeriod)->first()
            ?? $this->emptySummary($section);
    }

    /**
     * @param  Collection<int, Section>  $sections
     * @return Collection<int, array<string, mixed>>
     */
    public function summariesForSections(Collection $sections, GradingPeriod $gradingPeriod): Collection
    {
        if ($sections->isEmpty()) {
            return collect();
        }

        $sections->each(function (Section $section): void {
            $section->loadMissing([
                'schoolYear:id,name',
                'gradeLevel:id,name',
                'adviser:id,name',
            ]);
        });

        $sectionIds = $sections->pluck('id');

        $teacherLoadsBySection = TeacherLoad::query()
            ->with([
                'teacher:id,name',
                'subject:id,name,code',
                'gradeSubmissions' => fn ($query) => $query
                    ->where('grading_period_id', $gradingPeriod->id),
            ])
            ->join('subjects', 'subjects.id', '=', 'teacher_loads.subject_id')
            ->select('teacher_loads.*')
            ->whereIn('teacher_loads.section_id', $sectionIds)
            ->where('teacher_loads.school_year_id', $gradingPeriod->school_year_id)
            ->where('teacher_loads.is_active', true)
            ->orderBy('subjects.name')
            ->get()
            ->groupBy('section_id');

        $officialRostersBySection = SectionRoster::query()
            ->with('learner:id,lrn,last_name,first_name,middle_name')
            ->join('learners', 'learners.id', '=', 'section_rosters.learner_id')
            ->select('section_rosters.*')
            ->whereIn('section_rosters.section_id', $sectionIds)
            ->where('section_rosters.school_year_id', $gradingPeriod->school_year_id)
            ->where('section_rosters.is_official', true)
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name')
            ->get()
            ->groupBy('section_id');

        $reportCardRecordsBySection = ReportCardRecord::query()
            ->whereIn('section_id', $sectionIds)
            ->where('grading_period_id', $gradingPeriod->id)
            ->where('document_type', TemplateDocumentType::Sf9)
            ->orderByDesc('record_version')
            ->get()
            ->groupBy('section_id');

        return $sections
            ->map(fn (Section $section): array => $this->buildSummary(
                $section,
                $gradingPeriod,
                $teacherLoadsBySection->get($section->id, collect()),
                $officialRostersBySection->get($section->id, collect()),
                $reportCardRecordsBySection->get($section->id, collect()),
            ))
            ->values();
    }

    /**
     * @param  Collection<int, TeacherLoad>  $teacherLoads
     * @param  Collection<int, SectionRoster>  $officialRosters
     * @param  Collection<int, ReportCardRecord>  $reportCardRecords
     */
    private function buildSummary(
        Section $section,
        GradingPeriod $gradingPeriod,
        Collection $teacherLoads,
        Collection $officialRosters,
        Collection $reportCardRecords,
    ): array {
        $counts = [
            'missing' => 0,
            GradeSubmissionStatus::Draft->value => 0,
            GradeSubmissionStatus::Submitted->value => 0,
            GradeSubmissionStatus::Returned->value => 0,
            GradeSubmissionStatus::Approved->value => 0,
            GradeSubmissionStatus::Locked->value => 0,
        ];

        $missingSubjects = [];
        $lockBlockers = [];
        $lateSubmissionCount = 0;
        $deadline = $gradingPeriod->ends_on?->copy()->endOfDay();
        $now = now();

        foreach ($teacherLoads as $teacherLoad) {
            $gradeSubmission = $teacherLoad->gradeSubmissions->first();

            if ($gradeSubmission === null) {
                $counts['missing']++;
                $missingSubjects[] = sprintf('%s (%s)', $teacherLoad->subject->name, $teacherLoad->teacher->name);
                $lockBlockers[] = sprintf(
                    '%s is still missing for %s.',
                    $teacherLoad->subject->name,
                    $teacherLoad->teacher->name,
                );

                if ($deadline !== null && $now->greaterThan($deadline)) {
                    $lateSubmissionCount++;
                }

                continue;
            }

            $counts[$gradeSubmission->status->value]++;

            if (! in_array($gradeSubmission->status, [GradeSubmissionStatus::Approved, GradeSubmissionStatus::Locked], true)) {
                $lockBlockers[] = sprintf(
                    '%s is %s for %s.',
                    $teacherLoad->subject->name,
                    mb_strtolower($gradeSubmission->status->label()),
                    $teacherLoad->teacher->name,
                );
            }

            if ($this->deadlineEvaluator->isLate($gradeSubmission->submitted_at, $deadline, $now)) {
                $lateSubmissionCount++;
            }
        }

        $eligibleRosters = $officialRosters
            ->filter(fn (SectionRoster $sectionRoster): bool => $this->eligibilityService
                ->forGradingPeriod($sectionRoster, $gradingPeriod)['accepts_grade']);

        $latestRecordByRoster = $reportCardRecords
            ->groupBy('section_roster_id')
            ->map(fn (Collection $records): ?ReportCardRecord => $records->sortByDesc('record_version')->first());

        $officialRosterCount = $officialRosters->count();
        $requiredSf9RosterCount = $eligibleRosters->count();
        $finalizedSf9Count = $eligibleRosters
            ->filter(fn (SectionRoster $sectionRoster): bool => (bool) $latestRecordByRoster
                ->get($sectionRoster->id)?->is_finalized)
            ->count();

        if ($officialRosterCount === 0) {
            $lockBlockers[] = 'No official roster learners exist for this section.';
        } elseif ($requiredSf9RosterCount > 0 && $finalizedSf9Count !== $requiredSf9RosterCount) {
            $lockBlockers[] = sprintf(
                'SF9 records are finalized for %d of %d official learner%s.',
                $finalizedSf9Count,
                $requiredSf9RosterCount,
                $requiredSf9RosterCount === 1 ? '' : 's',
            );
        }

        $expectedSubmissionCount = $teacherLoads->count();
        $approvedSubmissionCount = $counts[GradeSubmissionStatus::Approved->value];
        $lockedSubmissionCount = $counts[GradeSubmissionStatus::Locked->value];
        $submissionCompletionCount = $approvedSubmissionCount + $lockedSubmissionCount;

        $isSubmissionComplete = $expectedSubmissionCount > 0
            && $submissionCompletionCount === $expectedSubmissionCount;
        $isLocked = $expectedSubmissionCount > 0
            && $lockedSubmissionCount === $expectedSubmissionCount;
        $isReadyForLock = ! $isLocked
            && $isSubmissionComplete
            && $officialRosterCount > 0
            && ($requiredSf9RosterCount === 0 || $finalizedSf9Count === $requiredSf9RosterCount);
        $isCompleted = $isReadyForLock || $isLocked;

        return [
            'model' => $section,
            'section_id' => $section->id,
            'section_name' => $section->name,
            'grade_level_name' => $section->gradeLevel->name,
            'school_year_name' => $section->schoolYear->name,
            'adviser_name' => $section->adviser?->name ?? 'Unassigned',
            'expected_submission_count' => $expectedSubmissionCount,
            'approved_submission_count' => $approvedSubmissionCount,
            'locked_submission_count' => $lockedSubmissionCount,
            'returned_submission_count' => $counts[GradeSubmissionStatus::Returned->value],
            'submitted_submission_count' => $counts[GradeSubmissionStatus::Submitted->value],
            'draft_submission_count' => $counts[GradeSubmissionStatus::Draft->value],
            'missing_submission_count' => $counts['missing'],
            'late_submission_count' => $lateSubmissionCount,
            'official_roster_count' => $officialRosterCount,
            'required_sf9_roster_count' => $requiredSf9RosterCount,
            'finalized_sf9_count' => $finalizedSf9Count,
            'completion_percentage' => $expectedSubmissionCount > 0
                ? (int) round(($submissionCompletionCount / $expectedSubmissionCount) * 100)
                : 0,
            'is_ready_for_lock' => $isReadyForLock,
            'is_locked' => $isLocked,
            'is_completed' => $isCompleted,
            'can_lock' => $isReadyForLock,
            'can_reopen' => $lockedSubmissionCount > 0,
            'status' => $this->statusPayload(
                $expectedSubmissionCount,
                $isLocked,
                $isReadyForLock,
                $lateSubmissionCount > 0,
            ),
            'counts' => $counts,
            'missing_subjects' => array_values($missingSubjects),
            'lock_blockers' => array_values(array_unique($lockBlockers)),
        ];
    }

    private function emptySummary(Section $section): array
    {
        $section->loadMissing([
            'schoolYear:id,name',
            'gradeLevel:id,name',
            'adviser:id,name',
        ]);

        return [
            'model' => $section,
            'section_id' => $section->id,
            'section_name' => $section->name,
            'grade_level_name' => $section->gradeLevel->name,
            'school_year_name' => $section->schoolYear->name,
            'adviser_name' => $section->adviser?->name ?? 'Unassigned',
            'expected_submission_count' => 0,
            'approved_submission_count' => 0,
            'locked_submission_count' => 0,
            'returned_submission_count' => 0,
            'submitted_submission_count' => 0,
            'draft_submission_count' => 0,
            'missing_submission_count' => 0,
            'late_submission_count' => 0,
            'official_roster_count' => 0,
            'required_sf9_roster_count' => 0,
            'finalized_sf9_count' => 0,
            'completion_percentage' => 0,
            'is_ready_for_lock' => false,
            'is_locked' => false,
            'is_completed' => false,
            'can_lock' => false,
            'can_reopen' => false,
            'status' => [
                'label' => 'Idle',
                'tone' => 'slate',
            ],
            'counts' => [
                'missing' => 0,
                GradeSubmissionStatus::Draft->value => 0,
                GradeSubmissionStatus::Submitted->value => 0,
                GradeSubmissionStatus::Returned->value => 0,
                GradeSubmissionStatus::Approved->value => 0,
                GradeSubmissionStatus::Locked->value => 0,
            ],
            'missing_subjects' => [],
            'lock_blockers' => [],
        ];
    }

    private function statusPayload(
        int $expectedSubmissionCount,
        bool $isLocked,
        bool $isReadyForLock,
        bool $hasLateSubmissions,
    ): array {
        if ($expectedSubmissionCount === 0) {
            return [
                'label' => 'Idle',
                'tone' => 'slate',
            ];
        }

        if ($isLocked) {
            return [
                'label' => 'Locked',
                'tone' => 'violet',
            ];
        }

        if ($isReadyForLock) {
            return [
                'label' => 'Ready',
                'tone' => 'emerald',
            ];
        }

        if ($hasLateSubmissions) {
            return [
                'label' => 'Late',
                'tone' => 'amber',
            ];
        }

        return [
            'label' => 'Open',
            'tone' => 'sky',
        ];
    }
}
