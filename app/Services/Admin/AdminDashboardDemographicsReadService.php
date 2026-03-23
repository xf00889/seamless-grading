<?php

namespace App\Services\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\LearnerSex;
use App\Models\SchoolYear;
use App\Models\SectionRoster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminDashboardDemographicsReadService
{
    public function build(?SchoolYear $schoolYear): array
    {
        $resolvedSchoolYear = $this->resolveSchoolYear($schoolYear);
        $referenceDate = $resolvedSchoolYear?->ends_on?->copy() ?? Carbon::today();
        $rows = $this->officialRosterDemographics($resolvedSchoolYear?->id ?? $schoolYear?->id);
        $officialRosterTotal = $rows->count();
        $sexItems = $this->sexItems($rows);
        $ageItems = $this->ageItems($rows, $referenceDate);
        $enrollmentStatusItems = $this->enrollmentStatusItems($rows);
        $gradeLevelItems = $this->gradeLevelItems($rows);

        return [
            'official_roster_total' => $officialRosterTotal,
            'reference_date_label' => $referenceDate->format('M d, Y'),
            'sex_chart' => [
                'type' => 'donut',
                'height' => 320,
                'labels' => $sexItems->pluck('label')->all(),
                'series' => $sexItems->pluck('value')->all(),
                'colors' => $sexItems->pluck('color')->all(),
                'totalLabel' => number_format($officialRosterTotal),
                'totalName' => 'Official',
                'emptyText' => 'No official roster demographic data is available.',
            ],
            'age_chart' => [
                'type' => 'bar',
                'height' => 320,
                'label' => 'Official roster age bands',
                'categories' => $ageItems->pluck('label')->all(),
                'values' => $ageItems->pluck('value')->all(),
                'colors' => $ageItems->pluck('color')->all(),
                'max' => $ageItems->max('value') ?? 0,
                'emptyText' => 'No birth-date data is available for age-band reporting.',
            ],
            'enrollment_status_chart' => [
                'type' => 'donut',
                'height' => 320,
                'labels' => $enrollmentStatusItems->pluck('label')->all(),
                'series' => $enrollmentStatusItems->pluck('value')->all(),
                'colors' => $enrollmentStatusItems->pluck('color')->all(),
                'totalLabel' => number_format($officialRosterTotal),
                'totalName' => 'Official',
                'emptyText' => 'No enrollment-status data is available for official roster reporting.',
            ],
            'grade_level_chart' => [
                'type' => 'bar',
                'height' => 320,
                'label' => 'Official roster by grade level',
                'categories' => $gradeLevelItems->pluck('label')->all(),
                'values' => $gradeLevelItems->pluck('value')->all(),
                'colors' => $gradeLevelItems->pluck('color')->all(),
                'max' => $gradeLevelItems->max('value') ?? 0,
                'emptyText' => 'No grade-level data is available for official roster reporting.',
            ],
        ];
    }

    private function resolveSchoolYear(?SchoolYear $schoolYear): ?SchoolYear
    {
        if ($schoolYear === null || $schoolYear->ends_on !== null) {
            return $schoolYear;
        }

        return SchoolYear::query()
            ->select(['id', 'ends_on'])
            ->find($schoolYear->id);
    }

    /**
     * @return Collection<int, array{
     *     sex: string,
     *     birth_date: string|null,
     *     enrollment_status: string,
     *     grade_level_name: string,
     *     grade_level_sort_order: int|null
     * }>
     */
    private function officialRosterDemographics(?int $schoolYearId): Collection
    {
        return $this->officialRosterQuery($schoolYearId)
            ->select([
                'learners.sex as learner_sex',
                'learners.birth_date as learner_birth_date',
                'section_rosters.enrollment_status as roster_enrollment_status',
                'grade_levels.name as grade_level_name',
                'grade_levels.sort_order as grade_level_sort_order',
            ])
            ->get()
            ->map(fn (SectionRoster $row): array => [
                'sex' => (string) $row->getAttribute('learner_sex'),
                'birth_date' => $row->getAttribute('learner_birth_date'),
                'enrollment_status' => (string) $row->getAttribute('roster_enrollment_status'),
                'grade_level_name' => (string) ($row->getAttribute('grade_level_name') ?? 'Unassigned'),
                'grade_level_sort_order' => $row->getAttribute('grade_level_sort_order') !== null
                    ? (int) $row->getAttribute('grade_level_sort_order')
                    : null,
            ]);
    }

    private function officialRosterQuery(?int $schoolYearId): Builder
    {
        return SectionRoster::query()
            ->join('learners', 'learners.id', '=', 'section_rosters.learner_id')
            ->join('sections', 'sections.id', '=', 'section_rosters.section_id')
            ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->where('section_rosters.is_official', true)
            ->when(
                $schoolYearId !== null,
                fn (Builder $query) => $query->where('section_rosters.school_year_id', $schoolYearId),
            );
    }

    /**
     * @param  Collection<int, array{
     *     sex: string,
     *     birth_date: string|null,
     *     enrollment_status: string,
     *     grade_level_name: string,
     *     grade_level_sort_order: int|null
     * }>  $rows
     * @return Collection<int, array{label: string, value: int, color: string}>
     */
    private function sexItems(Collection $rows): Collection
    {
        return collect([
            ['case' => LearnerSex::Male, 'color' => '#5b68b2'],
            ['case' => LearnerSex::Female, 'color' => '#de6c85'],
        ])->map(fn (array $item): array => [
            'label' => $item['case']->label(),
            'value' => $rows->where('sex', $item['case']->value)->count(),
            'color' => $item['color'],
        ]);
    }

    /**
     * @param  Collection<int, array{
     *     sex: string,
     *     birth_date: string|null,
     *     enrollment_status: string,
     *     grade_level_name: string,
     *     grade_level_sort_order: int|null
     * }>  $rows
     * @return Collection<int, array{key: string, label: string, value: int, color: string}>
     */
    private function ageItems(Collection $rows, Carbon $referenceDate): Collection
    {
        return collect($this->ageBands())
            ->map(function (array $band) use ($rows, $referenceDate): array {
                $count = $rows
                    ->filter(fn (array $row): bool => $this->ageBandKey($row['birth_date'], $referenceDate) === $band['key'])
                    ->count();

                return [
                    'key' => $band['key'],
                    'label' => $band['label'],
                    'value' => $count,
                    'color' => $band['color'],
                ];
            });
    }

    /**
     * @param  Collection<int, array{
     *     sex: string,
     *     birth_date: string|null,
     *     enrollment_status: string,
     *     grade_level_name: string,
     *     grade_level_sort_order: int|null
     * }>  $rows
     * @return Collection<int, array{label: string, value: int, color: string}>
     */
    private function enrollmentStatusItems(Collection $rows): Collection
    {
        return collect([
            ['case' => EnrollmentStatus::Active, 'color' => '#49a07d'],
            ['case' => EnrollmentStatus::Inactive, 'color' => '#8a94ad'],
            ['case' => EnrollmentStatus::TransferredOut, 'color' => '#f0a333'],
            ['case' => EnrollmentStatus::Dropped, 'color' => '#de6c85'],
        ])->map(fn (array $item): array => [
            'label' => $item['case']->label(),
            'value' => $rows->where('enrollment_status', $item['case']->value)->count(),
            'color' => $item['color'],
        ]);
    }

    /**
     * @param  Collection<int, array{
     *     sex: string,
     *     birth_date: string|null,
     *     enrollment_status: string,
     *     grade_level_name: string,
     *     grade_level_sort_order: int|null
     * }>  $rows
     * @return Collection<int, array{label: string, value: int, color: string}>
     */
    private function gradeLevelItems(Collection $rows): Collection
    {
        $palette = ['#5b68b2', '#7a84c4', '#9aa2d3', '#49a07d', '#f0a333', '#de6c85'];

        return $rows
            ->groupBy('grade_level_name')
            ->map(function (Collection $group, string $gradeLevelName): array {
                $sortOrder = $group->pluck('grade_level_sort_order')
                    ->filter(static fn (int|null $value): bool => $value !== null)
                    ->sort()
                    ->first();

                return [
                    'label' => $gradeLevelName,
                    'value' => $group->count(),
                    'sort_order' => $sortOrder ?? PHP_INT_MAX,
                ];
            })
            ->sortBy(fn (array $item): string => sprintf('%05d-%s', $item['sort_order'], $item['label']))
            ->values()
            ->map(fn (array $item, int $index): array => [
                'label' => $item['label'],
                'value' => $item['value'],
                'color' => $palette[$index % count($palette)],
            ]);
    }

    /**
     * @return array<int, array{key: string, label: string, color: string}>
     */
    private function ageBands(): array
    {
        return [
            ['key' => '10_and_below', 'label' => '10 and below', 'color' => '#8a94ad'],
            ['key' => '11_13', 'label' => '11-13', 'color' => '#5b68b2'],
            ['key' => '14_16', 'label' => '14-16', 'color' => '#49a07d'],
            ['key' => '17_plus', 'label' => '17+', 'color' => '#f0a333'],
            ['key' => 'unknown', 'label' => 'Unknown', 'color' => '#c5cede'],
        ];
    }

    private function ageBandKey(?string $birthDate, Carbon $referenceDate): string
    {
        if ($birthDate === null) {
            return 'unknown';
        }

        $age = Carbon::parse($birthDate)->diffInYears($referenceDate);

        return match (true) {
            $age <= 10 => '10_and_below',
            $age <= 13 => '11_13',
            $age <= 16 => '14_16',
            default => '17_plus',
        };
    }
}
