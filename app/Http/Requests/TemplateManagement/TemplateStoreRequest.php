<?php

namespace App\Http\Requests\TemplateManagement;

use App\Enums\TemplateDocumentType;
use App\Models\Template;
use App\Services\TemplateManagement\TemplateDefinitionRegistry;
use App\Services\TemplateManagement\TemplateWorkbookInspectionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TemplateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Template::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'document_type' => ['required', Rule::enum(TemplateDocumentType::class)],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,xlsm', 'max:'.config('templates.max_upload_kb')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasFile('file') || ! $this->filled('document_type')) {
                    return;
                }

                $documentType = TemplateDocumentType::from($this->string('document_type')->value());
                $allowedExtensions = app(TemplateDefinitionRegistry::class)->allowedExtensionsFor($documentType);
                $extension = strtolower($this->file('file')->getClientOriginalExtension());

                if (! in_array($extension, $allowedExtensions, true)) {
                    $validator->errors()->add(
                        'file',
                        'The selected file must match the '.$documentType->label().' template format: '.implode(', ', $allowedExtensions).'.',
                    );
                }

                $inspection = app(TemplateWorkbookInspectionService::class)
                    ->inspectUploadedFile($this->file('file'), $documentType);

                if (in_array(($inspection['status'] ?? null), ['mismatch', 'unreadable'], true)) {
                    foreach ($inspection['issues'] ?? [] as $issue) {
                        $validator->errors()->add('file', $issue);
                    }
                }
            },
        ];
    }
}
