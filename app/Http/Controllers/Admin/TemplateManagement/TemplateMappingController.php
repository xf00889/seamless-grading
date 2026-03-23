<?php

namespace App\Http\Controllers\Admin\TemplateManagement;

use App\Actions\Admin\TemplateManagement\UpdateTemplateFieldMapsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TemplateManagement\TemplateFieldMapUpdateRequest;
use App\Models\Template;
use App\Services\TemplateManagement\TemplateReadService;
use App\Support\TemplateManagement\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TemplateMappingController extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly TemplateReadService $readService,
    ) {}

    public function edit(Template $template): View
    {
        $this->authorize('updateMappings', $template);

        return view('admin.template-management.templates.mappings.edit', [
            'navigationItems' => $this->navigation->items(),
            ...$this->readService->mappingEditor($template),
        ]);
    }

    public function update(
        TemplateFieldMapUpdateRequest $request,
        Template $template,
        UpdateTemplateFieldMapsAction $action,
    ): RedirectResponse {
        $action->handle($request->user(), $template, $request->validated('mappings', []));

        return redirect()
            ->route('admin.template-management.templates.show', $template)
            ->with('status', 'Template mappings updated successfully.');
    }
}
