<?php

namespace App\Services\AdminMonitoring;

use App\Enums\TemplateDocumentType;
use App\Models\ApprovalLog;
use App\Models\GradingSheetExportAuditLog;
use App\Models\ImportBatch;
use App\Models\LearnerStatusAuditLog;
use App\Models\ReportCardRecordAuditLog;
use App\Models\TemplateAuditLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AuditLogReadService
{
    public function build(array $filters): array
    {
        $events = collect()
            ->merge($this->approvalLogEvents($filters))
            ->merge($this->templateAuditEvents($filters))
            ->merge($this->gradingSheetExportAuditEvents($filters))
            ->merge($this->reportCardRecordAuditEvents($filters))
            ->merge($this->learnerStatusAuditEvents($filters))
            ->merge($this->importBatchEvents($filters))
            ->sortByDesc(fn (array $event): int => $event['occurred_at']->getTimestamp())
            ->values();

        $page = Paginator::resolveCurrentPage('page');
        $perPage = 20;
        $items = $events->forPage($page, $perPage)->values();

        return [
            'filters' => [
                'from_date' => $filters['from_date'] ?? null,
                'to_date' => $filters['to_date'] ?? null,
                'user_id' => isset($filters['user_id']) ? (int) $filters['user_id'] : null,
                'action' => trim((string) ($filters['action'] ?? '')),
                'module' => trim((string) ($filters['module'] ?? '')),
            ],
            'availableUsers' => User::query()->orderBy('name')->get(['id', 'name']),
            'actionOptions' => [
                ['value' => '', 'label' => 'All actions'],
                ['value' => 'draft_saved', 'label' => 'Draft Saved'],
                ['value' => 'uploaded', 'label' => 'Uploaded'],
                ['value' => 'confirmed', 'label' => 'Confirmed'],
                ['value' => 'submitted', 'label' => 'Submitted'],
                ['value' => 'returned', 'label' => 'Returned'],
                ['value' => 'approved', 'label' => 'Approved'],
                ['value' => 'locked', 'label' => 'Locked'],
                ['value' => 'reopened', 'label' => 'Reopened'],
                ['value' => 'year_end_status_updated', 'label' => 'Year-End Status Updated'],
                ['value' => 'transferred_out_marked', 'label' => 'Transferred Out Marked'],
                ['value' => 'dropped_marked', 'label' => 'Dropped Marked'],
                ['value' => 'movement_cleared', 'label' => 'Movement Cleared'],
                ['value' => 'movement_corrected', 'label' => 'Movement Corrected'],
                ['value' => 'mappings_updated', 'label' => 'Mappings Updated'],
                ['value' => 'activated', 'label' => 'Activated'],
                ['value' => 'deactivated', 'label' => 'Deactivated'],
                ['value' => 'exported', 'label' => 'Exported'],
                ['value' => 'finalized', 'label' => 'Finalized'],
            ],
            'moduleOptions' => [
                ['value' => '', 'label' => 'All modules'],
                ['value' => 'sf1-imports', 'label' => 'SF1 Imports'],
                ['value' => 'grading-workflow', 'label' => 'Grading Workflow'],
                ['value' => 'templates', 'label' => 'Templates'],
                ['value' => 'grading-sheet-exports', 'label' => 'Grading Sheet Exports'],
                ['value' => 'sf9-records', 'label' => 'SF9 Records'],
                ['value' => 'sf10-records', 'label' => 'SF10 Records'],
                ['value' => 'year-end-status', 'label' => 'Year-End Status'],
                ['value' => 'learner-movements', 'label' => 'Learner Movements'],
            ],
            'events' => (new LengthAwarePaginator(
                $items,
                $events->count(),
                $perPage,
                $page,
                [
                    'path' => Paginator::resolveCurrentPath(),
                    'pageName' => 'page',
                ],
            ))->withQueryString(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function approvalLogEvents(array $filters): Collection
    {
        if (! $this->shouldIncludeModule($filters, 'grading-workflow')) {
            return collect();
        }

        return ApprovalLog::query()
            ->with([
                'actedBy:id,name',
                'gradeSubmission.teacherLoad.section.gradeLevel:id,name',
                'gradeSubmission.teacherLoad.subject:id,name,code',
                'gradeSubmission.teacherLoad.teacher:id,name',
                'gradeSubmission.gradingPeriod:id,quarter',
            ])
            ->when(
                isset($filters['user_id']),
                fn ($query) => $query->where('acted_by', (int) $filters['user_id']),
            )
            ->when(
                filled($filters['action'] ?? null),
                fn ($query) => $query->where('action', (string) $filters['action']),
            )
            ->when($this->fromDate($filters) !== null, fn ($query) => $query->where('created_at', '>=', $this->fromDate($filters)))
            ->when($this->toDate($filters) !== null, fn ($query) => $query->where('created_at', '<=', $this->toDate($filters)))
            ->latest()
            ->get()
            ->map(function (ApprovalLog $approvalLog): array {
                $submission = $approvalLog->gradeSubmission;
                $teacherLoad = $submission?->teacherLoad;
                $section = $teacherLoad?->section;
                $gradingPeriod = $submission?->gradingPeriod;

                return [
                    'occurred_at' => $approvalLog->created_at ?? now(),
                    'occurred_at_label' => $approvalLog->created_at?->format('M d, Y g:i A'),
                    'user_name' => $approvalLog->actedBy?->name ?? 'System',
                    'action' => $approvalLog->action->value,
                    'action_label' => $approvalLog->action->label(),
                    'module' => 'grading-workflow',
                    'module_label' => 'Grading Workflow',
                    'entity_label' => 'Grade Submission',
                    'context' => collect([
                        $section?->name,
                        $teacherLoad?->subject?->name,
                        $teacherLoad?->teacher?->name,
                        $gradingPeriod?->quarter?->label(),
                    ])->filter()->implode(' | '),
                    'remarks' => $approvalLog->remarks,
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function templateAuditEvents(array $filters): Collection
    {
        if (! $this->shouldIncludeModule($filters, 'templates')) {
            return collect();
        }

        return TemplateAuditLog::query()
            ->with([
                'actedBy:id,name',
                'template:id,name,version,document_type,grade_level_id',
                'template.gradeLevel:id,name',
            ])
            ->when(
                isset($filters['user_id']),
                fn ($query) => $query->where('acted_by', (int) $filters['user_id']),
            )
            ->when(
                filled($filters['action'] ?? null),
                fn ($query) => $query->where('action', (string) $filters['action']),
            )
            ->when($this->fromDate($filters) !== null, fn ($query) => $query->where('created_at', '>=', $this->fromDate($filters)))
            ->when($this->toDate($filters) !== null, fn ($query) => $query->where('created_at', '<=', $this->toDate($filters)))
            ->latest()
            ->get()
            ->map(fn (TemplateAuditLog $auditLog): array => [
                'occurred_at' => $auditLog->created_at ?? now(),
                'occurred_at_label' => $auditLog->created_at?->format('M d, Y g:i A'),
                'user_name' => $auditLog->actedBy?->name ?? 'System',
                'action' => $auditLog->action->value,
                'action_label' => $auditLog->action->label(),
                'module' => 'templates',
                'module_label' => 'Templates',
                'entity_label' => 'Template Version',
                'context' => collect([
                    $auditLog->template?->document_type?->label(),
                    $auditLog->template?->name,
                    $auditLog->template !== null ? 'v'.$auditLog->template->version : null,
                    $auditLog->template?->gradeLevel?->name,
                ])->filter()->implode(' | '),
                'remarks' => $auditLog->remarks,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function gradingSheetExportAuditEvents(array $filters): Collection
    {
        if (! $this->shouldIncludeModule($filters, 'grading-sheet-exports')) {
            return collect();
        }

        return GradingSheetExportAuditLog::query()
            ->with([
                'actedBy:id,name',
                'gradingSheetExport.teacherLoad.section:id,name',
                'gradingSheetExport.teacherLoad.subject:id,name,code',
                'gradingSheetExport.gradingPeriod:id,quarter',
            ])
            ->when(
                isset($filters['user_id']),
                fn ($query) => $query->where('acted_by', (int) $filters['user_id']),
            )
            ->when(
                filled($filters['action'] ?? null),
                fn ($query) => $query->where('action', (string) $filters['action']),
            )
            ->when($this->fromDate($filters) !== null, fn ($query) => $query->where('created_at', '>=', $this->fromDate($filters)))
            ->when($this->toDate($filters) !== null, fn ($query) => $query->where('created_at', '<=', $this->toDate($filters)))
            ->latest()
            ->get()
            ->map(fn (GradingSheetExportAuditLog $auditLog): array => [
                'occurred_at' => $auditLog->created_at ?? now(),
                'occurred_at_label' => $auditLog->created_at?->format('M d, Y g:i A'),
                'user_name' => $auditLog->actedBy?->name ?? 'System',
                'action' => $auditLog->action->value,
                'action_label' => $auditLog->action->label(),
                'module' => 'grading-sheet-exports',
                'module_label' => 'Grading Sheet Exports',
                'entity_label' => 'Grading Sheet Export',
                'context' => collect([
                    $auditLog->gradingSheetExport?->teacherLoad?->section?->name,
                    $auditLog->gradingSheetExport?->teacherLoad?->subject?->name,
                    $auditLog->gradingSheetExport?->gradingPeriod?->quarter?->label(),
                    $auditLog->gradingSheetExport !== null ? 'v'.$auditLog->gradingSheetExport->version : null,
                ])->filter()->implode(' | '),
                'remarks' => $auditLog->remarks,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function reportCardRecordAuditEvents(array $filters): Collection
    {
        if (! $this->shouldIncludeAnyModule($filters, ['sf9-records', 'sf10-records'])) {
            return collect();
        }

        return ReportCardRecordAuditLog::query()
            ->with([
                'actedBy:id,name',
                'reportCardRecord.section:id,name',
                'reportCardRecord.learner:id,last_name,first_name,middle_name',
                'reportCardRecord.gradingPeriod:id,quarter',
            ])
            ->when(
                isset($filters['user_id']),
                fn ($query) => $query->where('acted_by', (int) $filters['user_id']),
            )
            ->when(
                filled($filters['action'] ?? null),
                fn ($query) => $query->where('action', (string) $filters['action']),
            )
            ->when(
                ($filters['module'] ?? null) === 'sf9-records',
                fn ($query) => $query->whereHas(
                    'reportCardRecord',
                    fn ($recordQuery) => $recordQuery->where('document_type', TemplateDocumentType::Sf9),
                ),
            )
            ->when(
                ($filters['module'] ?? null) === 'sf10-records',
                fn ($query) => $query->whereHas(
                    'reportCardRecord',
                    fn ($recordQuery) => $recordQuery->where('document_type', TemplateDocumentType::Sf10),
                ),
            )
            ->when($this->fromDate($filters) !== null, fn ($query) => $query->where('created_at', '>=', $this->fromDate($filters)))
            ->when($this->toDate($filters) !== null, fn ($query) => $query->where('created_at', '<=', $this->toDate($filters)))
            ->latest()
            ->get()
            ->map(function (ReportCardRecordAuditLog $auditLog): array {
                $documentType = $auditLog->reportCardRecord?->document_type;
                $module = $documentType === TemplateDocumentType::Sf10 ? 'sf10-records' : 'sf9-records';
                $moduleLabel = $documentType === TemplateDocumentType::Sf10 ? 'SF10 Records' : 'SF9 Records';

                return [
                    'occurred_at' => $auditLog->created_at ?? now(),
                    'occurred_at_label' => $auditLog->created_at?->format('M d, Y g:i A'),
                    'user_name' => $auditLog->actedBy?->name ?? 'System',
                    'action' => $auditLog->action->value,
                    'action_label' => $auditLog->action->label(),
                    'module' => $module,
                    'module_label' => $moduleLabel,
                    'entity_label' => $documentType === TemplateDocumentType::Sf10 ? 'SF10 Record' : 'SF9 Record',
                    'context' => collect([
                        $auditLog->reportCardRecord?->section?->name,
                        $this->learnerName($auditLog->reportCardRecord?->learner),
                        $auditLog->reportCardRecord?->gradingPeriod?->quarter?->label(),
                        $auditLog->reportCardRecord !== null ? 'v'.$auditLog->reportCardRecord->record_version : null,
                    ])->filter()->implode(' | '),
                    'remarks' => $auditLog->remarks,
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function learnerStatusAuditEvents(array $filters): Collection
    {
        if (! $this->shouldIncludeAnyModule($filters, ['year-end-status', 'learner-movements'])) {
            return collect();
        }

        return LearnerStatusAuditLog::query()
            ->with([
                'actedBy:id,name',
                'sectionRoster.section:id,name',
                'sectionRoster.learner:id,last_name,first_name,middle_name',
            ])
            ->when(
                isset($filters['user_id']),
                fn ($query) => $query->where('acted_by', (int) $filters['user_id']),
            )
            ->when(
                filled($filters['action'] ?? null),
                fn ($query) => $query->where('action', (string) $filters['action']),
            )
            ->when(
                ($filters['module'] ?? null) === 'year-end-status',
                fn ($query) => $query->where('action', 'year_end_status_updated'),
            )
            ->when(
                ($filters['module'] ?? null) === 'learner-movements',
                fn ($query) => $query->where('action', '!=', 'year_end_status_updated'),
            )
            ->when($this->fromDate($filters) !== null, fn ($query) => $query->where('created_at', '>=', $this->fromDate($filters)))
            ->when($this->toDate($filters) !== null, fn ($query) => $query->where('created_at', '<=', $this->toDate($filters)))
            ->latest()
            ->get()
            ->map(function (LearnerStatusAuditLog $auditLog): array {
                $isYearEnd = $auditLog->action->value === 'year_end_status_updated';

                return [
                    'occurred_at' => $auditLog->created_at ?? now(),
                    'occurred_at_label' => $auditLog->created_at?->format('M d, Y g:i A'),
                    'user_name' => $auditLog->actedBy?->name ?? 'System',
                    'action' => $auditLog->action->value,
                    'action_label' => $auditLog->action->label(),
                    'module' => $isYearEnd ? 'year-end-status' : 'learner-movements',
                    'module_label' => $isYearEnd ? 'Year-End Status' : 'Learner Movements',
                    'entity_label' => $isYearEnd ? 'Learner Status' : 'Learner Movement',
                    'context' => collect([
                        $auditLog->sectionRoster?->section?->name,
                        $this->learnerName($auditLog->sectionRoster?->learner),
                        data_get($auditLog->metadata, 'current_year_end_status')
                            ?? data_get($auditLog->metadata, 'current_enrollment_status'),
                    ])->filter()->implode(' | '),
                    'remarks' => $auditLog->remarks,
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function importBatchEvents(array $filters): Collection
    {
        if (! $this->shouldIncludeModule($filters, 'sf1-imports')) {
            return collect();
        }

        $batches = ImportBatch::query()
            ->with([
                'importedBy:id,name',
                'confirmedBy:id,name',
                'section.gradeLevel:id,name',
            ])
            ->latest()
            ->get();

        return $batches
            ->flatMap(function (ImportBatch $importBatch): array {
                $events = [[
                    'occurred_at' => $importBatch->created_at ?? now(),
                    'occurred_at_label' => $importBatch->created_at?->format('M d, Y g:i A'),
                    'user_id' => $importBatch->imported_by,
                    'user_name' => $importBatch->importedBy?->name ?? 'System',
                    'action' => 'uploaded',
                    'action_label' => 'Uploaded',
                    'module' => 'sf1-imports',
                    'module_label' => 'SF1 Imports',
                    'entity_label' => 'Import Batch',
                    'context' => collect([
                        $importBatch->section?->name,
                        $importBatch->section?->gradeLevel?->name,
                        $importBatch->source_file_name,
                    ])->filter()->implode(' | '),
                    'remarks' => 'SF1 import batch uploaded for review.',
                ]];

                if ($importBatch->confirmed_at !== null) {
                    $events[] = [
                        'occurred_at' => $importBatch->confirmed_at,
                        'occurred_at_label' => $importBatch->confirmed_at->format('M d, Y g:i A'),
                        'user_id' => $importBatch->confirmed_by ?? $importBatch->imported_by,
                        'user_name' => $importBatch->confirmedBy?->name ?? $importBatch->importedBy?->name ?? 'System',
                        'action' => 'confirmed',
                        'action_label' => 'Confirmed',
                        'module' => 'sf1-imports',
                        'module_label' => 'SF1 Imports',
                        'entity_label' => 'Import Batch',
                        'context' => collect([
                            $importBatch->section?->name,
                            $importBatch->section?->gradeLevel?->name,
                            $importBatch->source_file_name,
                        ])->filter()->implode(' | '),
                        'remarks' => 'SF1 import batch confirmed into official rosters.',
                    ];
                }

                return $events;
            })
            ->filter(function (array $event) use ($filters): bool {
                if (
                    isset($filters['user_id'])
                    && (int) ($event['user_id'] ?? 0) !== (int) $filters['user_id']
                ) {
                    return false;
                }

                if (filled($filters['action'] ?? null) && $event['action'] !== (string) $filters['action']) {
                    return false;
                }

                $fromDate = $this->fromDate($filters);
                $toDate = $this->toDate($filters);

                if ($fromDate !== null && $event['occurred_at']->lt($fromDate)) {
                    return false;
                }

                return $toDate === null || ! $event['occurred_at']->gt($toDate);
            })
            ->values();
    }

    private function shouldIncludeModule(array $filters, string $module): bool
    {
        return blank($filters['module'] ?? null) || $filters['module'] === $module;
    }

    /**
     * @param  array<int, string>  $modules
     */
    private function shouldIncludeAnyModule(array $filters, array $modules): bool
    {
        return blank($filters['module'] ?? null) || in_array($filters['module'], $modules, true);
    }

    private function fromDate(array $filters): ?Carbon
    {
        if (blank($filters['from_date'] ?? null)) {
            return null;
        }

        return Carbon::parse((string) $filters['from_date'])->startOfDay();
    }

    private function toDate(array $filters): ?Carbon
    {
        if (blank($filters['to_date'] ?? null)) {
            return null;
        }

        return Carbon::parse((string) $filters['to_date'])->endOfDay();
    }

    private function learnerName(mixed $learner): ?string
    {
        if ($learner === null) {
            return null;
        }

        return trim(sprintf(
            '%s, %s%s',
            $learner->last_name,
            $learner->first_name,
            $learner->middle_name !== null ? ' '.mb_substr($learner->middle_name, 0, 1).'.' : '',
        ));
    }
}
