<?php

namespace App\Http\Requests\Adviser;

use App\Enums\GradeSubmissionStatus;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvisorySectionTrackerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            ? ($this->user()?->can('viewAsAdviser', $section) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'missing',
                    ...array_map(
                        static fn (GradeSubmissionStatus $status): string => $status->value,
                        GradeSubmissionStatus::cases(),
                    ),
                ]),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
