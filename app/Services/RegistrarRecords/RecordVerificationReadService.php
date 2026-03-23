<?php

namespace App\Services\RegistrarRecords;

use App\Enums\TemplateDocumentType;
use App\Models\Learner;
use App\Models\ReportCardRecord;
use App\Models\ReportCardRecordAuditLog;

class RecordVerificationReadService
{
    public function __construct(
        private readonly FinalRecordScopeResolver $scopeResolver,
    ) {}

    public function build(ReportCardRecord $reportCardRecord): array
    {
        $this->scopeResolver->assertRecordIsVisible($reportCardRecord);

        $reportCardRecord->loadMissing([
            'learner',
            'schoolYear:id,name',
            'section:id,name,grade_level_id',
            'section.gradeLevel:id,name',
            'gradingPeriod:id,quarter',
            'template:id,name,version',
            'generatedBy:id,name',
            'finalizedBy:id,name',
            'auditLogs.actedBy:id,name',
        ]);

        $history = $this->scopeResolver->query()
            ->with([
                'schoolYear:id,name',
                'section:id,name',
                'gradingPeriod:id,quarter',
            ])
            ->where('learner_id', $reportCardRecord->learner_id)
            ->where('document_type', $reportCardRecord->document_type)
            ->orderByDesc('finalized_at')
            ->orderByDesc('generated_at')
            ->orderByDesc('id')
            ->get();

        return [
            'record' => [
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
                'period_label' => $reportCardRecord->document_type === TemplateDocumentType::Sf9
                    ? ($reportCardRecord->gradingPeriod?->quarter?->label() ?? 'Quarter record')
                    : 'Year-end record',
                'record_version' => $reportCardRecord->record_version,
                'template_name' => $reportCardRecord->template?->name ?? 'Unknown template',
                'template_version' => $reportCardRecord->template_version,
                'generated_at' => $reportCardRecord->generated_at?->format('M d, Y g:i A'),
                'generated_by' => $reportCardRecord->generatedBy?->name ?? 'Unknown user',
                'finalized_at' => $reportCardRecord->finalized_at?->format('M d, Y g:i A'),
                'finalized_by' => $reportCardRecord->finalizedBy?->name ?? 'Unknown user',
                'finalization_status' => [
                    'label' => 'Finalized official record',
                    'tone' => 'emerald',
                ],
            ],
            'workflowContext' => $this->workflowContext($reportCardRecord),
            'subjectRows' => $this->subjectRows($reportCardRecord),
            'auditTimeline' => $reportCardRecord->auditLogs
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (ReportCardRecordAuditLog $auditLog): array => [
                    'action_label' => $auditLog->action->label(),
                    'occurred_at' => $auditLog->created_at?->format('M d, Y g:i A'),
                    'actor' => $auditLog->actedBy?->name ?? 'System',
                    'remarks' => $auditLog->remarks,
                ])
                ->all(),
            'versionHistory' => $history
                ->map(fn (ReportCardRecord $historyRecord): array => [
                    'id' => $historyRecord->id,
                    'school_year_name' => $historyRecord->schoolYear?->name ?? 'Unknown school year',
                    'section_name' => $historyRecord->section?->name ?? 'Unknown section',
                    'period_label' => $historyRecord->document_type === TemplateDocumentType::Sf9
                        ? ($historyRecord->gradingPeriod?->quarter?->label() ?? 'Quarter record')
                        : 'Year-end record',
                    'record_version' => $historyRecord->record_version,
                    'finalized_at' => $historyRecord->finalized_at?->format('M d, Y g:i A'),
                    'is_current' => $historyRecord->is($reportCardRecord),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function workflowContext(ReportCardRecord $reportCardRecord): array
    {
        $context = [
            ['label' => 'Source state', 'value' => 'Finalized official record'],
            ['label' => 'Generated by', 'value' => $reportCardRecord->generatedBy?->name ?? 'Unknown user'],
            ['label' => 'Finalized by', 'value' => $reportCardRecord->finalizedBy?->name ?? 'Unknown user'],
        ];

        if ($reportCardRecord->document_type === TemplateDocumentType::Sf9) {
            $context[] = ['label' => 'Grading period', 'value' => $reportCardRecord->gradingPeriod?->quarter?->label() ?? 'Quarter record'];
            if (filled(data_get($reportCardRecord->payload, 'general_average'))) {
                $context[] = ['label' => 'General average', 'value' => (string) data_get($reportCardRecord->payload, 'general_average')];
            }
            if (filled(data_get($reportCardRecord->payload, 'promotion_remarks'))) {
                $context[] = ['label' => 'Promotion remarks', 'value' => (string) data_get($reportCardRecord->payload, 'promotion_remarks')];
            }
        }

        if ($reportCardRecord->document_type === TemplateDocumentType::Sf10) {
            if (filled(data_get($reportCardRecord->payload, 'year_end_status.label'))) {
                $context[] = ['label' => 'Year-end status', 'value' => (string) data_get($reportCardRecord->payload, 'year_end_status.label')];
            }
            if (filled(data_get($reportCardRecord->payload, 'general_average'))) {
                $context[] = ['label' => 'General average', 'value' => (string) data_get($reportCardRecord->payload, 'general_average')];
            }
            if ($reportCardRecord->gradingPeriod?->quarter !== null) {
                $context[] = ['label' => 'Source final quarter', 'value' => $reportCardRecord->gradingPeriod->quarter->label()];
            }
        }

        return $context;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function subjectRows(ReportCardRecord $reportCardRecord): array
    {
        return collect(data_get($reportCardRecord->payload, 'subject_rows', []))
            ->map(fn (array $row): array => [
                'subject_name' => (string) ($row['subject_name'] ?? 'Unknown subject'),
                'grade_value' => (string) ($row['final_rating'] ?? $row['grade'] ?? ''),
                'remarks' => isset($row['remarks']) ? (string) $row['remarks'] : null,
                'action_taken' => isset($row['action_taken']) ? (string) $row['action_taken'] : null,
            ])
            ->all();
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
