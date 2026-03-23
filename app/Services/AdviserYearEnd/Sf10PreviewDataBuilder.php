<?php

namespace App\Services\AdviserYearEnd;

use App\Enums\TemplateDocumentType;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\SystemSetting;
use App\Services\TemplateManagement\TemplateMappingStatusEvaluator;
use Illuminate\Support\Facades\Storage;

class Sf10PreviewDataBuilder
{
    public function __construct(
        private readonly AdviserYearEndContextResolver $contextResolver,
        private readonly YearEndGradeReadinessService $readinessService,
        private readonly ActiveSf10TemplateResolver $templateResolver,
        private readonly TemplateMappingStatusEvaluator $mappingStatusEvaluator,
        private readonly Sf10DraftReviewContextBuilder $draftReviewContextBuilder,
    ) {}

    public function build(Section $section, SectionRoster $sectionRoster): array
    {
        $this->contextResolver->assertSectionRosterScope($section, $sectionRoster);

        $section->loadMissing([
            'schoolYear:id,name',
            'gradeLevel:id,name',
            'adviser:id,name',
        ]);
        $sectionRoster->loadMissing(['learner', 'yearEndStatusSetBy:id,name']);

        $yearEndSummary = $this->readinessService->detail($section, $sectionRoster);
        $template = $this->templateResolver->resolve($section);
        $templateMappingStatus = $template !== null
            ? $this->mappingStatusEvaluator->evaluate($template)
            : null;
        $templateFileMissing = $template !== null
            && (blank($template->file_path) || ! Storage::disk($template->file_disk)->exists($template->file_path));
        $schoolName = (string) (SystemSetting::query()
            ->where('key', 'school.profile')
            ->first()?->value['name'] ?? config('app.name'));
        $sourceHash = $this->sourceHash($section, $sectionRoster, $schoolName, $yearEndSummary);
        $blockers = $this->blockers(
            $yearEndSummary,
            $template,
            $templateMappingStatus,
            $templateFileMissing,
        );
        $draftReviewContext = $this->draftReviewContextBuilder->build(
            $sectionRoster,
            $yearEndSummary['final_grading_period'],
            $template,
            $sourceHash,
            $blockers,
        );

        return [
            'section' => $section,
            'sectionRoster' => $sectionRoster,
            'school_name' => $schoolName,
            'template' => $template === null ? null : [
                'model' => $template,
                'id' => $template->id,
                'name' => $template->name,
                'scope' => $template->gradeLevel?->name ?? 'All grade levels',
                'version' => $template->version,
                'mapping_status' => $templateMappingStatus['status'],
                'mapping_summary' => $templateMappingStatus,
                'is_file_missing' => $templateFileMissing,
            ],
            'year_end' => $yearEndSummary,
            'draft_review' => $draftReviewContext['draft_review'],
            'finalization_status' => $draftReviewContext['finalization_status'],
            'blockers' => array_values(array_unique($blockers)),
            'finalization_blockers' => $draftReviewContext['finalization_blockers'],
            'preview_ready' => $blockers === [],
            'export_ready' => $blockers === [],
            'finalize_ready' => $draftReviewContext['finalize_ready'],
            'history' => $draftReviewContext['history'],
            'source_hash' => $sourceHash,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function blockers(
        array $yearEndSummary,
        mixed $template,
        ?array $templateMappingStatus,
        bool $templateFileMissing,
    ): array {
        $blockers = $yearEndSummary['full_year_blockers'];

        if ($yearEndSummary['year_end_status'] === null) {
            $blockers[] = 'Set a learner year-end status before preparing or exporting SF10.';
        }

        if ($template === null) {
            $blockers[] = 'No active SF10 template exists for this learner scope. Activate a validated SF10 template before preparing or exporting.';
        } elseif ($templateMappingStatus !== null && $templateMappingStatus['status']['value'] !== 'complete') {
            $blockers[] = 'The active SF10 template has incomplete or broken field mappings. Repair and reactivate the template before exporting.';
        }

        if ($templateFileMissing) {
            $blockers[] = 'The active SF10 template file is missing from storage and cannot be used for export.';
        }

        if ($yearEndSummary['approved_year_end_rows'] === []) {
            $blockers[] = 'No approved final year-end subject data exists yet for this learner.';
        }

        return $blockers;
    }

    private function sourceHash(
        Section $section,
        SectionRoster $sectionRoster,
        string $schoolName,
        array $yearEndSummary,
    ): string {
        return hash('sha256', json_encode([
            'section_id' => $section->id,
            'section_roster_id' => $sectionRoster->id,
            'school_year_id' => $section->school_year_id,
            'document_type' => TemplateDocumentType::Sf10->value,
            'school_name' => $schoolName,
            'year_end_status' => $yearEndSummary['year_end_status']['value'] ?? null,
            'general_average' => $yearEndSummary['general_average'],
            'subject_rows' => $yearEndSummary['approved_year_end_rows'],
        ], JSON_THROW_ON_ERROR));
    }
}
