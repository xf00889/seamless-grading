<?php

namespace App\Http\Requests\Adviser;

use App\Enums\EnrollmentStatus;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LearnerMovementIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            ? ($this->user()?->can('viewLearnerMovementsAsAdviser', $section) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => [
                'nullable',
                Rule::in([
                    '',
                    EnrollmentStatus::Active->value,
                    EnrollmentStatus::TransferredOut->value,
                    EnrollmentStatus::Dropped->value,
                ]),
            ],
        ];
    }
}
