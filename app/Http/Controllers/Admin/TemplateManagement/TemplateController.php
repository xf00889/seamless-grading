<?php

namespace App\Http\Controllers\Admin\TemplateManagement;

use App\Actions\Admin\TemplateManagement\ActivateTemplateAction;
use App\Actions\Admin\TemplateManagement\CreateTemplateAction;
use App\Actions\Admin\TemplateManagement\DeactivateTemplateAction;
use App\Enums\TemplateDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\TemplateManagement\ActivateTemplateRequest;
use App\Http\Requests\TemplateManagement\DeactivateTemplateRequest;
use App\Http\Requests\TemplateManagement\TemplateIndexRequest;
use App\Http\Requests\TemplateManagement\TemplateStoreRequest;
use App\Models\GradeLevel;
use App\Models\Template;
use App\Services\TemplateManagement\TemplateDefinitionRegistry;
use App\Services\TemplateManagement\TemplateReadService;
use App\Support\TemplateManagement\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TemplateController extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly TemplateReadService $readService,
        private readonly TemplateDefinitionRegistry $definitionRegistry,
    ) {}

    public function index(TemplateIndexRequest $request): View
    {
        return view('admin.template-management.templates.index', [
            'navigationItems' => $this->navigation->items(),
            ...$this->readService->index($request->validated()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Template::class);

        return view('admin.template-management.templates.create', [
            'navigationItems' => $this->navigation->items(),
            'documentTypes' => TemplateDocumentType::cases(),
            'gradeLevels' => GradeLevel::query()->orderBy('sort_order')->get(['id', 'name']),
            'uploadRules' => collect(TemplateDocumentType::cases())
                ->map(fn (TemplateDocumentType $documentType): array => [
                    'label' => $documentType->label(),
                    'extensions' => $this->definitionRegistry->allowedExtensionsFor($documentType),
                ])
                ->all(),
        ]);
    }

    public function store(
        TemplateStoreRequest $request,
        CreateTemplateAction $action,
    ): RedirectResponse {
        $template = $action->handle($request->user(), $request->validated());

        return redirect()
            ->route('admin.template-management.templates.show', $template)
            ->with('status', 'Template version uploaded successfully.');
    }

    public function show(Template $template): View
    {
        $this->authorize('view', $template);

        return view('admin.template-management.templates.show', [
            'navigationItems' => $this->navigation->items(),
            ...$this->readService->show($template),
        ]);
    }

    public function history(Template $template): View
    {
        $this->authorize('history', $template);

        return view('admin.template-management.templates.history', [
            'navigationItems' => $this->navigation->items(),
            ...$this->readService->history($template),
        ]);
    }

    public function activate(
        ActivateTemplateRequest $request,
        Template $template,
        ActivateTemplateAction $action,
    ): RedirectResponse {
        $action->handle($request->user(), $template);

        return redirect()
            ->route('admin.template-management.templates.show', $template)
            ->with('status', 'Template activated successfully.');
    }

    public function deactivate(
        DeactivateTemplateRequest $request,
        Template $template,
        DeactivateTemplateAction $action,
    ): RedirectResponse {
        $action->handle($request->user(), $template);

        return redirect()
            ->route('admin.template-management.templates.show', $template)
            ->with('status', 'Template deactivated successfully.');
    }
}
