<?php

namespace App\Services\RegistrarRecords;

use App\Enums\TemplateDocumentType;
use App\Models\ReportCardRecord;
use App\Support\Dashboard\BarChartPresenter;

class RegistrarDashboardReadService
{
    public function __construct(
        private readonly FinalRecordScopeResolver $scopeResolver,
        private readonly BarChartPresenter $barChartPresenter,
    ) {}

    public function build(): array
    {
        $baseQuery = $this->scopeResolver->query();

        $totals = [
            'records' => (clone $baseQuery)->count(),
            'sf9' => (clone $baseQuery)->where('document_type', TemplateDocumentType::Sf9)->count(),
            'sf10' => (clone $baseQuery)->where('document_type', TemplateDocumentType::Sf10)->count(),
            'learners' => (clone $baseQuery)->distinct('learner_id')->count('learner_id'),
        ];

        $latestRecords = $this->scopeResolver->query()
            ->with([
                'learner:id,first_name,last_name,middle_name',
                'schoolYear:id,name',
                'section:id,name,grade_level_id',
                'section.gradeLevel:id,name',
                'gradingPeriod:id,quarter',
            ])
            ->orderByDesc('finalized_at')
            ->limit(5)
            ->get()
            ->map(fn (ReportCardRecord $record): array => [
                'id' => $record->id,
                'learner_name' => trim(sprintf(
                    '%s, %s%s',
                    $record->learner?->last_name ?? 'Unknown',
                    $record->learner?->first_name ?? 'learner',
                    $record->learner?->middle_name !== null ? ' '.mb_substr($record->learner->middle_name, 0, 1).'.' : '',
                )),
                'document_type' => [
                    'label' => $record->document_type->label(),
                    'tone' => $record->document_type->tone(),
                ],
                'context' => collect([
                    $record->schoolYear?->name,
                    $record->section?->gradeLevel?->name,
                    $record->section?->name,
                    $record->document_type === TemplateDocumentType::Sf9
                        ? $record->gradingPeriod?->quarter?->label()
                        : 'Year-end',
                ])->filter()->implode(' · '),
                'finalized_at' => $record->finalized_at?->format('M d, Y g:i A') ?? 'Unknown finalization time',
            ])
            ->all();

        $gradeLevelDistribution = $this->scopeResolver->query()
            ->join('sections', 'sections.id', '=', 'report_card_records.section_id')
            ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->selectRaw('grade_levels.name as label, count(*) as total')
            ->groupBy('grade_levels.id', 'grade_levels.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->label,
                'value' => (int) $row->total,
                'value_label' => (string) $row->total,
                'emphasis' => false,
            ])
            ->all();

        $chartItems = $gradeLevelDistribution !== []
            ? $this->barChartPresenter->present($gradeLevelDistribution)
            : $this->barChartPresenter->present([
                ['label' => 'SF9', 'value' => $totals['sf9'], 'value_label' => number_format($totals['sf9'])],
                ['label' => 'SF10', 'value' => $totals['sf10'], 'value_label' => number_format($totals['sf10']), 'emphasis' => true],
            ]);

        return [
            'headline' => [
                'eyebrow' => 'Registrar workspace',
                'title' => 'Registrar Dashboard',
                'description' => 'Monitor the finalized official record repository without reopening grading or approval workflows.',
            ],
            'metrics' => [
                [
                    'eyebrow' => 'Repository scope',
                    'label' => 'Finalized records',
                    'value' => number_format($totals['records']),
                    'description' => 'Official finalized learner records available to the registrar.',
                    'icon' => 'archive',
                    'tone' => 'indigo',
                    'action_label' => 'Open repository',
                    'action_href' => route('registrar.records.index'),
                ],
                [
                    'eyebrow' => 'Quarter records',
                    'label' => 'SF9 records',
                    'value' => number_format($totals['sf9']),
                    'description' => 'Finalized quarterly report-card versions stored in the repository.',
                    'icon' => 'dashboard',
                    'tone' => 'emerald',
                ],
                [
                    'eyebrow' => 'Permanent records',
                    'label' => 'SF10 records',
                    'value' => number_format($totals['sf10']),
                    'description' => 'Year-end finalized permanent record versions visible in MVP scope.',
                    'icon' => 'template',
                    'tone' => 'amber',
                ],
                [
                    'eyebrow' => 'Learner coverage',
                    'label' => 'Distinct learners',
                    'value' => number_format($totals['learners']),
                    'description' => 'Unique learners with at least one finalized official record in the repository.',
                    'icon' => 'users',
                    'tone' => 'slate',
                ],
            ],
            'chart' => [
                'eyebrow' => 'Repository distribution',
                'title' => $gradeLevelDistribution !== [] ? 'Records By Grade Level' : 'Document Split',
                'description' => $gradeLevelDistribution !== []
                    ? 'A quick view of where finalized record volume is currently concentrated.'
                    : 'The repository is ready, but the grade-level distribution has not formed yet.',
                'items' => $chartItems,
            ],
            'focus' => $latestRecords !== []
                ? [
                    'eyebrow' => 'Latest finalization',
                    'title' => $latestRecords[0]['learner_name'],
                    'description' => 'The newest finalized official record is ready for verification and historical review.',
                    'meta' => $latestRecords[0]['context'],
                    'action_label' => 'Verify latest record',
                    'action_href' => route('registrar.records.show', ['report_card_record' => $latestRecords[0]['id']]),
                ]
                : [
                    'eyebrow' => 'Repository status',
                    'title' => 'No finalized official records are visible yet.',
                    'description' => 'The registrar repository will populate once approved data has been generated and finalized into official records.',
                    'meta' => 'Read-only visibility',
                    'action_label' => 'Open repository',
                    'action_href' => route('registrar.records.index'),
                ],
            'latestRecords' => $latestRecords,
        ];
    }
}
