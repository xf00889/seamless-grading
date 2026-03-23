<?php

namespace App\Http\Requests\Adviser;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AdviserDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAdviserDashboard', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
            'grading_period_id' => ['nullable', 'integer', 'exists:grading_periods,id'],
        ];
    }
}
