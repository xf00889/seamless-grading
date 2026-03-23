<?php

namespace App\Http\Requests\AcademicSetup;

use App\Models\GradeLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GradeLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('grade_level') !== null
            ? $this->user()?->can('update', $this->route('grade_level')) ?? false
            : $this->user()?->can('create', GradeLevel::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('grade_levels', 'code')->ignore($this->route('grade_level')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('grade_levels', 'sort_order')->ignore($this->route('grade_level')),
            ],
        ];
    }
}
