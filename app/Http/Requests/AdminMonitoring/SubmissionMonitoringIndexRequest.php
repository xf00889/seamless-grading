<?php

namespace App\Http\Requests\AdminMonitoring;

use App\Enums\GradeSubmissionStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmissionMonitoringIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewSubmissionMonitoring', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'grading_period_id' => ['nullable', 'integer', 'exists:grading_periods,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'adviser_id' => ['nullable', 'integer', 'exists:users,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'missing',
                    ...collect(GradeSubmissionStatus::cases())->map(fn (GradeSubmissionStatus $status): string => $status->value)->all(),
                ]),
            ],
        ];
    }
}
