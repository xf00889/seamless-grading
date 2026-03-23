<?php

namespace App\Services\RegistrarRecords;

use App\Enums\TemplateDocumentType;
use App\Models\Learner;
use App\Models\ReportCardRecord;
use Illuminate\Support\Collection;

class LearnerRecordHistoryReadService
{
    public function __construct(
        private readonly FinalRecordScopeResolver $scopeResolver,
    ) {}

    public function build(Learner $learner): array
    {
        $this->scopeResolver->assertLearnerHasVisibleRecords($learner);

        $records = $this->scopeResolver->query()
            ->with([
                'schoolYear:id,name',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name',
                'gradingPeriod:id,quarter',
                'template:id,name,version',
                'generatedBy:id,name',
                'finalizedBy:id,name',
            ])
            ->where('learner_id', $learner->id)
            ->orderByDesc('finalized_at')
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->get();

        return [
            'learner' => [
                'id' => $learner->id,
                'name' => $this->learnerName($learner),
                'lrn' => $learner->lrn,
            ],
            'totals' => [
                'records' => $records->count(),
                'sf9' => $records->where('document_type', TemplateDocumentType::Sf9)->count(),
                'sf10' => $records->where('document_type', TemplateDocumentType::Sf10)->count(),
            ],
            'groups' => $records
                ->groupBy(fn (ReportCardRecord $reportCardRecord): string => $reportCardRecord->document_type->value)
                ->map(function (Collection $group): array {
                    /** @var ReportCardRecord $first */
                    $first = $group->first();

                    return [
                        'document_type' => [
                            'value' => $first->document_type->value,
                            'label' => $first->document_type->label(),
                            'tone' => $first->document_type->tone(),
                        ],
                        'records' => $group->map(fn (ReportCardRecord $reportCardRecord): array => [
                            'id' => $reportCardRecord->id,
                            'school_year_name' => $reportCardRecord->schoolYear?->name ?? 'Unknown school year',
                            'grade_level_name' => $reportCardRecord->section?->gradeLevel?->name ?? 'Unknown grade level',
                            'section_name' => $reportCardRecord->section?->name ?? 'Unknown section',
                            'period_label' => $reportCardRecord->document_type === TemplateDocumentType::Sf9
                                ? ($reportCardRecord->gradingPeriod?->quarter?->label() ?? 'Quarter record')
                                : 'Year-end record',
                            'record_version' => $reportCardRecord->record_version,
                            'template_version' => $reportCardRecord->template_version,
                            'template_name' => $reportCardRecord->template?->name ?? 'Unknown template',
                            'generated_at' => $reportCardRecord->generated_at?->format('M d, Y g:i A'),
                            'finalized_at' => $reportCardRecord->finalized_at?->format('M d, Y g:i A'),
                            'generated_by' => $reportCardRecord->generatedBy?->name ?? 'Unknown user',
                            'finalized_by' => $reportCardRecord->finalizedBy?->name ?? 'Unknown user',
                        ])->values()->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function learnerName(Learner $learner): string
    {
        return trim(sprintf(
            '%s, %s%s',
            $learner->last_name,
            $learner->first_name,
            $learner->middle_name !== null ? ' '.mb_substr($learner->middle_name, 0, 1).'.' : '',
        ));
    }
}
