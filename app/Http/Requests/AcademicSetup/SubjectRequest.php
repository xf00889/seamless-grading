<?php

namespace App\Http\Requests\AcademicSetup;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('subject') !== null
            ? $this->user()?->can('update', $this->route('subject')) ?? false
            : $this->user()?->can('create', Subject::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subjects', 'code')->ignore($this->route('subject')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'is_core' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_core' => $this->boolean('is_core'),
        ]);
    }
}
