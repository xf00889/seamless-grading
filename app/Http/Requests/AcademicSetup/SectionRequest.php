<?php

namespace App\Http\Requests\AcademicSetup;

use App\Enums\RoleName;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('section') !== null
            ? $this->user()?->can('update', $this->route('section')) ?? false
            : $this->user()?->can('create', Section::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'school_year_id' => ['required', 'exists:school_years,id'],
            'grade_level_id' => ['required', 'exists:grade_levels,id'],
            'adviser_id' => ['nullable', 'exists:users,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sections', 'name')
                    ->where(fn ($query) => $query->where('school_year_id', $this->input('school_year_id')))
                    ->ignore($this->route('section')),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $adviserId = $this->integer('adviser_id');

                if ($adviserId === 0) {
                    return;
                }

                $adviser = User::query()->find($adviserId);

                if ($adviser !== null && ! $adviser->hasRole(RoleName::Adviser->value)) {
                    $validator->errors()->add('adviser_id', 'The selected adviser must have the adviser role.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'adviser_id' => $this->filled('adviser_id') ? $this->integer('adviser_id') : null,
        ]);
    }
}
