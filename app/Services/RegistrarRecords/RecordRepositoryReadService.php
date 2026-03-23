<?php

namespace App\Services\RegistrarRecords;

use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\Learner;
use App\Models\ReportCardRecord;
use App\Models\SchoolYear;
use App\Models\Section;
use Illuminate\Database\Eloquent\Builder;

class RecordRepositoryReadService
{
    public function __construct(
        private readonly FinalRecordScopeResolver $scopeResolver,
    ) {}

    public function build(array $filters): array
    {
        $query = $this->scopeResolver->query()
            ->with([
                'learner:id,first_name,last_name,middle_name,lrn',
                'schoolYear:id,name',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name',
                'gradingPeriod:id,quarter',
                'template:id,name,version',
                'generatedBy:id,name',
                'finalizedBy:id,name',
            ]);

        $this->applyFilters($query, $filters);

        $records = $query
            ->orderByDesc('finalized_at')
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ReportCardRecord $reportCardRecord): array => $this->mapRecord($reportCardRecord));

        $totalsQuery = $this->scopeResolver->query();
        $this->applyFilters($totalsQuery, $filters);

        return [
            'filters' => [
                'search' => $filters['search'] ?? '',
                'lrn' => $filters['lrn'] ?? '',
                'school_year_id' => isset($filters['school_year_id']) ? (int) $filters['school_year_id'] : null,
                'grade_level_id' => isset($filters['grade_level_id']) ? (int) $filters['grade_level_id'] : null,
                'section_id' => isset($filters['section_id']) ? (int) $filters['section_id'] : null,
                'document_type' => $filters['document_type'] ?? '',
                'finalization_status' => $filters['finalization_status'] ?? 'finalized',
            ],
            'schoolYears' => SchoolYear::query()->orderByDesc('name')->get(['id', 'name']),
            'gradeLevels' => GradeLevel::query()->orderBy('sort_order')->get(['id', 'name']),
            'sections' => Section::query()
                ->with(['gradeLevel:id,name', 'schoolYear:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'grade_level_id', 'school_year_id']),
            'documentTypeOptions' => [
                ['value' => '', 'label' => 'All finalized types'],
                ['value' => TemplateDocumentType::Sf9->value, 'label' => TemplateDocumentType::Sf9->label()],
                ['value' => TemplateDocumentType::Sf10->value, 'label' => TemplateDocumentType::Sf10->label()],
            ],
            'finalizationStatusOptions' => [
                ['value' => 'finalized', 'label' => 'Finalized only'],
            ],
            'totals' => [
                'records' => (clone $totalsQuery)->count(),
                'sf9' => (clone $totalsQuery)->where('document_type', TemplateDocumentType::Sf9)->count(),
                'sf10' => (clone $totalsQuery)->where('document_type', TemplateDocumentType::Sf10)->count(),
            ],
            'records' => $records,
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $lrn = trim((string) ($filters['lrn'] ?? ''));

        if ($search !== '') {
            $terms = collect(preg_split('/\s+/', $search) ?: [])
                ->filter(fn (?string $term): bool => filled($term))
                ->values();

            $query->whereHas('learner', function (Builder $learnerQuery) use ($terms): void {
                $terms->each(function (string $term) use ($learnerQuery): void {
                    $learnerQuery->where(function (Builder $termQuery) use ($term): void {
                        $termQuery
                            ->where('first_name', 'like', '%'.$term.'%')
                            ->orWhere('last_name', 'like', '%'.$term.'%')
                            ->orWhere('middle_name', 'like', '%'.$term.'%')
                            ->orWhere('lrn', 'like', '%'.$term.'%');
                    });
                });
            });
        }

        if ($lrn !== '') {
            $query->whereHas('learner', fn (Builder $learnerQuery) => $learnerQuery->where('lrn', 'like', '%'.$lrn.'%'));
        }

        if (isset($filters['school_year_id'])) {
            $query->where('school_year_id', (int) $filters['school_year_id']);
        }

        if (isset($filters['section_id'])) {
            $query->where('section_id', (int) $filters['section_id']);
        }

        if (isset($filters['grade_level_id'])) {
            $query->whereHas('section', fn (Builder $sectionQuery) => $sectionQuery->where('grade_level_id', (int) $filters['grade_level_id']));
        }

        if (filled($filters['document_type'] ?? null)) {
            $query->where('document_type', (string) $filters['document_type']);
        }

        if (($filters['finalization_status'] ?? 'finalized') === 'finalized') {
            $query->where('is_finalized', true);
        }
    }

    private function mapRecord(ReportCardRecord $reportCardRecord): array
    {
        return [
            'id' => $reportCardRecord->id,
            'learner_id' => $reportCardRecord->learner_id,
            'learner_name' => $this->learnerName($reportCardRecord->learner),
            'lrn' => $reportCardRecord->learner?->lrn ?? 'Unknown',
            'school_year_name' => $reportCardRecord->schoolYear?->name ?? 'Unknown school year',
            'grade_level_name' => $reportCardRecord->section?->gradeLevel?->name ?? 'Unknown grade level',
            'section_name' => $reportCardRecord->section?->name ?? 'Unknown section',
            'document_type' => [
                'value' => $reportCardRecord->document_type->value,
                'label' => $reportCardRecord->document_type->label(),
                'tone' => $reportCardRecord->document_type->tone(),
            ],
            'record_version' => $reportCardRecord->record_version,
            'template_version' => $reportCardRecord->template_version,
            'period_label' => $reportCardRecord->document_type === TemplateDocumentType::Sf9
                ? ($reportCardRecord->gradingPeriod?->quarter?->label() ?? 'Quarter record')
                : 'Year-end record',
            'generated_at' => $reportCardRecord->generated_at?->format('M d, Y g:i A'),
            'finalized_at' => $reportCardRecord->finalized_at?->format('M d, Y g:i A'),
            'generated_by' => $reportCardRecord->generatedBy?->name ?? 'Unknown user',
            'finalized_by' => $reportCardRecord->finalizedBy?->name ?? 'Unknown user',
            'finalization_status' => [
                'label' => 'Finalized',
                'tone' => 'emerald',
            ],
        ];
    }

    private function learnerName(?Learner $learner): string
    {
        if ($learner === null) {
            return 'Unknown learner';
        }

        return trim(sprintf(
            '%s, %s%s',
            $learner->last_name,
            $learner->first_name,
            $learner->middle_name !== null ? ' '.mb_substr($learner->middle_name, 0, 1).'.' : '',
        ));
    }
}
