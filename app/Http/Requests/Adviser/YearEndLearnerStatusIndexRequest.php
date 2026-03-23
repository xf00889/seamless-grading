<?php

namespace App\Http\Requests\Adviser;

use App\Enums\LearnerYearEndStatus;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class YearEndLearnerStatusIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            ? ($this->user()?->can('viewYearEndAsAdviser', $section) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    '',
                    'unset',
                    ...collect(LearnerYearEndStatus::cases())->map->value->all(),
                ]),
            ],
        ];
    }
}
