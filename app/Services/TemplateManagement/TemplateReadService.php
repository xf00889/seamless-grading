<?php

namespace App\Services\TemplateManagement;

use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\Template;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class TemplateReadService
{
    public function __construct(
        private readonly TemplateMappingStatusEvaluator $statusEvaluator,
    ) {}

    public function overview(): array
    {
        $templates = Template::query()->with('fieldMaps')->get();

        return [
            'resourceCards' => collect(TemplateDocumentType::cases())
                ->map(fn (TemplateDocumentType $documentType): array => [
                    'document_type' => $documentType->value,
                    'label' => $documentType->label(),
                    'count' => $templates->where('document_type', $documentType)->count(),
                    'status' => $templates->where('document_type', $documentType)->where('is_active', true)->count().' active',
                    'description' => match ($documentType) {
                        TemplateDocumentType::GradingSheet => 'Manage worksheet layouts and cell mappings for grading sheet exports.',
                        TemplateDocumentType::Sf9 => 'Maintain report-card template versions and approved field mappings.',
                        TemplateDocumentType::Sf10 => 'Manage year-end permanent-record templates and mappings for adviser SF10 draft exports.',
                    },
                ])
                ->all(),
        ];
    }

    public function index(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $documentType = isset($filters['document_type']) && $filters['document_type'] !== ''
            ? TemplateDocumentType::from($filters['document_type'])
            : null;
        $gradeLevelId = isset($filters['grade_level_id']) ? (int) $filters['grade_level_id'] : null;
        $status = trim((string) ($filters['status'] ?? ''));
        $page = max((int) ($filters['page'] ?? 1), 1);

        $templates = Template::query()
            ->with(['gradeLevel:id,name', 'fieldMaps'])
            ->when($search !== '', fn ($query) => $query->where(function ($templateQuery) use ($search): void {
                $templateQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            }))
            ->when($documentType !== null, fn ($query) => $query->where('document_type', $documentType))
            ->when($gradeLevelId !== null, fn ($query) => $query->where('grade_level_id', $gradeLevelId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Template $template): array => $this->presentTemplate($template));

        $perPage = 10;
        $items = $templates->forPage($page, $perPage)->values();
        $paginator = new Paginator($items, $templates->count(), $perPage, $page, [
            'path' => route('admin.template-management.templates.index'),
        ]);
        $paginator->appends(array_filter([
            'search' => $search !== '' ? $search : null,
            'document_type' => $documentType?->value,
            'grade_level_id' => $gradeLevelId,
            'status' => $status !== '' ? $status : null,
        ]));

        return [
            'templates' => $paginator,
            'filters' => [
                'search' => $search,
                'document_type' => $documentType?->value,
                'grade_level_id' => $gradeLevelId,
                'status' => $status,
            ],
            'documentTypes' => TemplateDocumentType::cases(),
            'gradeLevels' => GradeLevel::query()->orderBy('sort_order')->get(['id', 'name']),
        ];
    }

    public function show(Template $template): array
    {
        $template->loadMissing([
            'gradeLevel:id,name',
            'fieldMaps',
            'auditLogs' => fn ($query) => $query->with('actedBy:id,name')->latest()->limit(10),
        ]);

        return [
            'template' => $this->presentTemplate($template),
            'history' => $this->familyHistory($template),
        ];
    }

    public function history(Template $template): array
    {
        $template->loadMissing('gradeLevel:id,name');

        return [
            'template' => $this->presentTemplate($template),
            'history' => $this->familyHistory($template),
        ];
    }

    public function mappingEditor(Template $template): array
    {
        $template->loadMissing(['gradeLevel:id,name', 'fieldMaps']);

        return [
            'template' => $this->presentTemplate($template),
        ];
    }

    public function presentTemplate(Template $template): array
    {
        $template->loadMissing(['gradeLevel:id,name', 'fieldMaps']);
        $mappingStatus = $this->statusEvaluator->evaluate($template);

        return [
            'model' => $template,
            'id' => $template->id,
            'code' => $template->code,
            'name' => $template->name,
            'description' => $template->description,
            'document_type' => [
                'value' => $template->document_type->value,
                'label' => $template->document_type->label(),
                'tone' => $template->document_type->tone(),
            ],
            'grade_level_id' => $template->grade_level_id,
            'version' => $template->version,
            'scope' => $template->gradeLevel?->name ?? 'All grade levels',
            'scope_key' => $template->scope_key,
            'file_path' => $template->file_path,
            'file_disk' => $template->file_disk,
            'is_active' => $template->is_active,
            'status' => $template->is_active
                ? ['label' => 'Active', 'tone' => 'emerald']
                : ['label' => 'Inactive', 'tone' => 'slate'],
            'activated_at' => $template->activated_at?->format('M d, Y g:i A'),
            'deactivated_at' => $template->deactivated_at?->format('M d, Y g:i A'),
            'created_at' => $template->created_at?->format('M d, Y g:i A'),
            'mapping_status' => $mappingStatus['status'],
            'mapping_summary' => $mappingStatus,
            'workbook_inspection' => $mappingStatus['workbook_inspection'],
            'audit_logs' => $template->relationLoaded('auditLogs')
                ? $template->auditLogs->map(fn ($log): array => [
                    'action' => $log->action->label(),
                    'acted_by' => $log->actedBy?->name ?? 'Unknown user',
                    'remarks' => $log->remarks,
                    'created_at' => $log->created_at?->format('M d, Y g:i A'),
                ])->all()
                : [],
        ];
    }

    private function familyHistory(Template $template): array
    {
        return Template::query()
            ->with(['gradeLevel:id,name', 'fieldMaps'])
            ->where('document_type', $template->document_type)
            ->where('scope_key', $template->scope_key)
            ->where('code', $template->code)
            ->orderByDesc('version')
            ->get()
            ->map(fn (Template $version): array => $this->presentTemplate($version))
            ->all();
    }
}
