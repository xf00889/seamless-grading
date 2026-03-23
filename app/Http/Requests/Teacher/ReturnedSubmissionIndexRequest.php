<?php

namespace App\Http\Requests\Teacher;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ReturnedSubmissionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewTeacherReturnedSubmissions', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'grading_period_id' => ['nullable', 'integer', 'exists:grading_periods,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
