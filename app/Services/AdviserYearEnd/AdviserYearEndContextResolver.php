<?php

namespace App\Services\AdviserYearEnd;

use App\Enums\TemplateDocumentType;
use App\Models\GradingPeriod;
use App\Models\ReportCardRecord;
use App\Models\Section;
use App\Models\SectionRoster;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdviserYearEndContextResolver
{
    public function assertSectionRosterScope(Section $section, SectionRoster $sectionRoster): void
    {
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
        SectionRoster $sectionRoster,
        ReportCardRecord $reportCardRecord,
    ): void {
        $this->assertSectionRosterScope($section, $sectionRoster);

        if (
            $reportCardRecord->section_roster_id !== $sectionRoster->id
            || $reportCardRecord->section_id !== $section->id
            || $reportCardRecord->school_year_id !== $section->school_year_id
            || $reportCardRecord->document_type !== TemplateDocumentType::Sf10
        ) {
            throw new NotFoundHttpException;
        }
    }

    public function finalGradingPeriod(Section $section): ?GradingPeriod
    {
        return GradingPeriod::query()
            ->where('school_year_id', $section->school_year_id)
            ->orderByDesc('quarter')
            ->first();
    }
}
