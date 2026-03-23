<?php

namespace App\Services\AdviserYearEnd;

use App\Enums\LearnerYearEndStatus;
use App\Models\Section;
use App\Models\SectionRoster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class YearEndLearnerStatusReadService
{
    public function __construct(
        private readonly YearEndGradeReadinessService $readinessService,
    ) {}

    public function build(Section $section, array $filters): array
    {
        $section->loadMissing([
            'schoolYear:id,name',
            'gradeLevel:id,name',
            'adviser:id,name',
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $rosterQuery = SectionRoster::query()
            ->with(['learner', 'yearEndStatusSetBy:id,name'])
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
                            ->orWhere('learners.lrn', 'like', '%'.$search.'%');
                    });
                },
            )
            ->when(
                $status === 'unset',
                fn (Builder $query) => $query->whereNull('section_rosters.year_end_status'),
            )
            ->when(
                $status !== '' && $status !== 'unset',
                fn (Builder $query) => $query->where('section_rosters.year_end_status', $status),
            )
            ->orderBy('learners.last_name')
            ->orderBy('learners.first_name');

        $allRosters = (clone $rosterQuery)->get();
        $allSummaries = $this->readinessService->summariesForRosters($section, $allRosters);

        $sectionRosters = $rosterQuery
            ->paginate(10)
            ->withQueryString();

        $pageSummaries = $this->readinessService
            ->summariesForRosters($section, collect($sectionRosters->items()))
            ->keyBy('section_roster_id');

        $sectionRosters->through(fn (SectionRoster $sectionRoster): array => $pageSummaries->get($sectionRoster->id));

        return [
            'section' => $section,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => [
                ['value' => '', 'label' => 'All statuses'],
                ['value' => 'unset', 'label' => 'Status not set'],
                ...collect(LearnerYearEndStatus::cases())
                    ->map(fn (LearnerYearEndStatus $yearEndStatus): array => [
                        'value' => $yearEndStatus->value,
                        'label' => $yearEndStatus->label(),
                    ])
                    ->all(),
            ],
            'sectionRosters' => $sectionRosters,
            'totals' => $this->totals($allSummaries),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $summaries
     * @return array<string, int>
     */
    private function totals(Collection $summaries): array
    {
        return [
            'official_learners' => $summaries->count(),
            'final_quarter_ready' => (int) $summaries->filter(fn (array $summary): bool => $summary['final_quarter_ready'])->count(),
            'full_year_ready' => (int) $summaries->filter(fn (array $summary): bool => $summary['full_year_ready'])->count(),
            'status_set' => (int) $summaries->filter(fn (array $summary): bool => $summary['year_end_status'] !== null)->count(),
            'promoted' => (int) $summaries->filter(fn (array $summary): bool => $summary['year_end_status']['value'] ?? LearnerYearEndStatus::Promoted->value === null)->count(),
            'retained' => (int) $summaries->filter(fn (array $summary): bool => $summary['year_end_status']['value'] ?? LearnerYearEndStatus::Retained->value === null)->count(),
            'transferred_out' => (int) $summaries->filter(fn (array $summary): bool => $summary['year_end_status']['value'] ?? LearnerYearEndStatus::TransferredOut->value === null)->count(),
            'dropped' => (int) $summaries->filter(fn (array $summary): bool => $summary['year_end_status']['value'] ?? LearnerYearEndStatus::Dropped->value === null)->count(),
        ];
    }
}
