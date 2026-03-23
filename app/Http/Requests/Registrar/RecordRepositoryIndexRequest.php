<?php

namespace App\Http\Requests\Registrar;

use App\Enums\TemplateDocumentType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordRepositoryIndexRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim((string) $this->input('search', '')),
            'lrn' => trim((string) $this->input('lrn', '')),
            'document_type' => $this->filled('document_type') ? (string) $this->input('document_type') : null,
            'finalization_status' => $this->filled('finalization_status')
                ? (string) $this->input('finalization_status')
                : 'finalized',
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('viewRegistrarRecords', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'lrn' => ['nullable', 'string', 'max:32'],
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'document_type' => ['nullable', Rule::in([
                TemplateDocumentType::Sf9->value,
                TemplateDocumentType::Sf10->value,
            ])],
            'finalization_status' => ['required', Rule::in(['finalized'])],
        ];
    }
}
