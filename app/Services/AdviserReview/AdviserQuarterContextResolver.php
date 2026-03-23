<?php

namespace App\Services\AdviserReview;

use App\Enums\TemplateDocumentType;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\ReportCardRecord;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdviserQuarterContextResolver
{
    public function resolve(User $adviser, array $filters): array
    {
        $availableSchoolYears = SchoolYear::query()
            ->select(['id', 'name', 'starts_on', 'is_active'])
            ->whereHas('sections', fn ($query) => $query->where('adviser_id', $adviser->id))
            ->orderByDesc('starts_on')
            ->get();

        $requestedSchoolYearId = isset($filters['school_year_id']) ? (int) $filters['school_year_id'] : null;
        $selectedSchoolYearId = $this->selectedSchoolYearId($availableSchoolYears, $requestedSchoolYearId);

        $availableGradingPeriods = $selectedSchoolYearId === null
            ? collect()
            : GradingPeriod::query()
                ->select(['id', 'school_year_id', 'quarter', 'is_open'])
                ->where('school_year_id', $selectedSchoolYearId)
                ->orderBy('quarter')
                ->get();

        $requestedGradingPeriodId = isset($filters['grading_period_id']) ? (int) $filters['grading_period_id'] : null;
        $selectedGradingPeriodId = $this->selectedGradingPeriodId($availableGradingPeriods, $requestedGradingPeriodId);

        return [
            'availableSchoolYears' => $availableSchoolYears,
            'availableGradingPeriods' => $availableGradingPeriods,
            'selectedSchoolYearId' => $selectedSchoolYearId,
            'selectedGradingPeriod' => $availableGradingPeriods->firstWhere('id', $selectedGradingPeriodId),
            'selectedGradingPeriodId' => $selectedGradingPeriodId,
        ];
    }

    public function assertSectionPeriodScope(Section $section, GradingPeriod $gradingPeriod): void
    {
        if ($section->school_year_id !== $gradingPeriod->school_year_id) {
            throw new NotFoundHttpException;
        }
    }

    public function assertSubmissionScope(
        Section $section,
        GradingPeriod $gradingPeriod,
        GradeSubmission $gradeSubmission,
    ): void {
        $this->assertSectionPeriodScope($section, $gradingPeriod);

        if (
            $gradeSubmission->teacherLoad->section_id !== $section->id
            || $gradeSubmission->teacherLoad->school_year_id !== $section->school_year_id
            || $gradeSubmission->grading_period_id !== $gradingPeriod->id
        ) {
            throw new NotFoundHttpException;
        }
    }

    public function assertSectionRosterScope(
        Section $section,
        GradingPeriod $gradingPeriod,
        SectionRoster $sectionRoster,
    ): void {
        $this->assertSectionPeriodScope($section, $gradingPeriod);

        if (
            $sectionRoster->section_id !== $section->id
            || $sectionRoster->school_year_id !== $section->school_year_id
            || ! $sectionRoster->is_official
        ) {
            throw new NotFoundHttpException;
        }
    }

    public function assertReportCardRecordScope(
        Section $section,
        GradingPeriod $gradingPeriod,
        SectionRoster $sectionRoster,
        ReportCardRecord $reportCardRecord,
    ): void {
        $this->assertSectionRosterScope($section, $gradingPeriod, $sectionRoster);

        if (
            $reportCardRecord->section_roster_id !== $sectionRoster->id
            || $reportCardRecord->grading_period_id !== $gradingPeriod->id
            || $reportCardRecord->document_type !== TemplateDocumentType::Sf9
        ) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * @param  Collection<int, SchoolYear>  $availableSchoolYears
     */
    private function selectedSchoolYearId(Collection $availableSchoolYears, ?int $requestedSchoolYearId): ?int
    {
        if ($requestedSchoolYearId !== null && $availableSchoolYears->contains('id', $requestedSchoolYearId)) {
            return $requestedSchoolYearId;
        }

        return $availableSchoolYears->firstWhere('is_active', true)?->id
            ?? $availableSchoolYears->first()?->id;
    }

    /**
     * @param  Collection<int, GradingPeriod>  $availableGradingPeriods
     */
    private function selectedGradingPeriodId(Collection $availableGradingPeriods, ?int $requestedGradingPeriodId): ?int
    {
        if ($requestedGradingPeriodId !== null && $availableGradingPeriods->contains('id', $requestedGradingPeriodId)) {
            return $requestedGradingPeriodId;
        }

        return $availableGradingPeriods->firstWhere('is_open', true)?->id
            ?? $availableGradingPeriods->first()?->id;
    }
}
