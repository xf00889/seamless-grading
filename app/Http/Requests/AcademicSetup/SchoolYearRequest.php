<?php

namespace App\Http\Requests\AcademicSetup;

use App\Models\SchoolYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('school_year') !== null
            ? $this->user()?->can('update', $this->route('school_year')) ?? false
            : $this->user()?->can('create', SchoolYear::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('school_years', 'name')->ignore($this->route('school_year')),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ];
    }
}
