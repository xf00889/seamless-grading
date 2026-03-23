<?php

namespace App\Services\LearnerMovement;

use App\Enums\EnrollmentStatus;
use App\Models\GradingPeriod;
use App\Models\Section;
use App\Models\SectionRoster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LearnerMovementReadService
{
    public function __construct(
        private readonly LearnerMovementEligibilityService $eligibilityService,
    ) {}

    public function build(Section $section, array $filters): array
    {
        $section->loadMissing([
            'schoolYear:id,name,starts_on,ends_on,is_active',
            'gradeLevel:id,name',
            'adviser:id,name',
        ]);

        $gradingPeriods = GradingPeriod::query()
            ->where('school_year_id', $section->school_year_id)
            ->orderBy('quarter')
            ->get();

        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $rosterQuery = SectionRoster::query()
            ->with([
                'learner',
                'movementRecordedBy:id,name',
                'yearEndStatusSetBy:id,name',
            ])
            ->join('learners', 'learners.id', '=', 'section_rosters.learner_id')
            ->select('section_rosters.*')
            ->where('section_rosters.section_id', $section->id)
            ->where('section_rosters.school_year_id', $section->school_year_id)
            ->where('section_rosters.is_official', true)
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(function (Builder $builder) use ($search): void {
                        $builder
                            ->where('learners.last_name', 'like', '%'.$search.'%')
                            ->orWhere('learners.first_name', 'like', '%'.$search.'%')
                            ->orWhere('learners.middle_name', 'like', '%'.$search.'%')
                            ->orWhere('learners.lrn', 'like', '%'.$search.'%');
                    });
                },
            )
            ->when(
                $status !== '',
                fn (Builder $query) => $query->where('section_rosters.enrollment_status', $status),
            )
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name');

        $allRows = $this->rows(
            (clone $rosterQuery)->get(),
            $gradingPeriods,
        );

        $sectionRosters = $rosterQuery
            ->paginate(10)
            ->withQueryString();

        $pageRows = $this->rows(
            collect($sectionRosters->items()),
            $gradingPeriods,
        )->keyBy('section_roster_id');

        $sectionRosters->through(fn (SectionRoster $sectionRoster): array => $pageRows->get($sectionRoster->id));

        return [
            'section' => $section,
            'gradingPeriods' => $gradingPeriods,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => [
                ['value' => '', 'label' => 'All official learners'],
                ['value' => EnrollmentStatus::Active->value, 'label' => EnrollmentStatus::Active->label()],
                ['value' => EnrollmentStatus::TransferredOut->value, 'label' => EnrollmentStatus::TransferredOut->label()],
                ['value' => EnrollmentStatus::Dropped->value, 'label' => EnrollmentStatus::Dropped->label()],
            ],
            'sectionRosters' => $sectionRosters,
            'totals' => [
                'official_learners' => $allRows->count(),
                'active' => (int) $allRows->filter(fn (array $row): bool => $row['movement_status']['value'] === EnrollmentStatus::Active->value)->count(),
                'transferred_out' => (int) $allRows->filter(fn (array $row): bool => $row['movement_status']['value'] === EnrollmentStatus::TransferredOut->value)->count(),
                'dropped' => (int) $allRows->filter(fn (array $row): bool => $row['movement_status']['value'] === EnrollmentStatus::Dropped->value)->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, SectionRoster>  $sectionRosters
     * @param  Collection<int, GradingPeriod>  $gradingPeriods
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(Collection $sectionRosters, Collection $gradingPeriods): Collection
    {
        return $sectionRosters
            ->map(function (SectionRoster $sectionRoster) use ($gradingPeriods): array {
                $summary = $this->eligibilityService->summary($sectionRoster);
                $periodRules = $gradingPeriods
                    ->map(fn (GradingPeriod $gradingPeriod): array => [
                        'quarter' => $gradingPeriod->quarter->label(),
                        ...$this->eligibilityService->forGradingPeriod($sectionRoster, $gradingPeriod),
                    ])
                    ->values();

                $eligiblePeriods = $periodRules
                    ->filter(fn (array $periodRule): bool => $periodRule['accepts_grade'])
                    ->pluck('quarter')
                    ->all();

                $blockedPeriods = $periodRules
                    ->reject(fn (array $periodRule): bool => $periodRule['accepts_grade'])
                    ->pluck('quarter')
                    ->all();

                $firstBlockedPeriod = $periodRules->first(fn (array $periodRule): bool => ! $periodRule['accepts_grade']);
                $yearEndStatus = $sectionRoster->year_end_status;

                return [
                    'section_roster_id' => $sectionRoster->id,
                    'learner' => [
                        'name' => trim(sprintf(
                            '%s, %s%s',
                            $sectionRoster->learner->last_name,
                            $sectionRoster->learner->first_name,
                            $sectionRoster->learner->middle_name !== null
                                ? ' '.mb_substr($sectionRoster->learner->middle_name, 0, 1).'.'
                                : '',
                        )),
                        'lrn' => $sectionRoster->learner->lrn,
                    ],
                    'movement_status' => $summary['status'],
                    'effective_date' => $summary['effective_date'],
                    'effective_date_label' => $summary['effective_date_label'],
                    'movement_reason' => $summary['reason'],
                    'movement_recorded_at' => $sectionRoster->movement_recorded_at?->format('M d, Y g:i A'),
                    'movement_recorded_by' => $sectionRoster->movementRecordedBy?->name,
                    'eligibility_note' => $firstBlockedPeriod['reason'] ?? 'This learner remains grade-eligible for every configured grading period in the school year.',
                    'eligible_periods' => $eligiblePeriods,
                    'blocked_periods' => $blockedPeriods,
                    'year_end_status' => $yearEndStatus === null ? null : [
                        'value' => $yearEndStatus->value,
                        'label' => $yearEndStatus->label(),
                        'tone' => $yearEndStatus->tone(),
                    ],
                    'form' => [
                        'status' => in_array($summary['status']['value'], [
                            EnrollmentStatus::TransferredOut->value,
                            EnrollmentStatus::Dropped->value,
                        ], true)
                            ? $summary['status']['value']
                            : EnrollmentStatus::Active->value,
                        'effective_date' => $summary['effective_date'],
                        'reason' => $summary['reason'],
                    ],
                ];
            })
            ->values();
    }
}
