<?php

namespace App\Http\Requests\TemplateManagement;

use App\Enums\TemplateDocumentType;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Template::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'document_type' => ['nullable', Rule::enum(TemplateDocumentType::class)],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
