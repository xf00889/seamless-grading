<?php

namespace App\Http\Requests\Sf1Imports;

use App\Enums\LearnerSex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveSf1ImportRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('resolve', $this->route('import_batch')) ?? false;
    }

    public function rules(): array
    {
        return [
            'learner_id' => ['nullable', 'exists:learners,id'],
            'lrn' => ['required', 'digits:12'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:50'],
            'sex' => ['required', Rule::enum(LearnerSex::class)],
            'birth_date' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'learner_id' => $this->filled('learner_id') ? (int) $this->input('learner_id') : null,
            'lrn' => preg_replace('/\D+/', '', (string) $this->input('lrn')),
            'last_name' => trim((string) $this->input('last_name')),
            'first_name' => trim((string) $this->input('first_name')),
            'middle_name' => $this->filled('middle_name') ? trim((string) $this->input('middle_name')) : null,
            'suffix' => $this->filled('suffix') ? trim((string) $this->input('suffix')) : null,
            'sex' => strtolower((string) $this->input('sex')),
        ]);
    }
}
