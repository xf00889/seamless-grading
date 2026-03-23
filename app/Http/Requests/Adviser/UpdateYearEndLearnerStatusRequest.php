<?php

namespace App\Http\Requests\Adviser;

use App\Enums\LearnerYearEndStatus;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateYearEndLearnerStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            ? ($this->user()?->can('manageYearEndAsAdviser', $section) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LearnerYearEndStatus::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
            'search_filter' => ['nullable', 'string', 'max:255'],
            'status_filter' => ['nullable', Rule::enum(LearnerYearEndStatus::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('status')) {
                    return;
                }

                $status = LearnerYearEndStatus::from($this->string('status')->value());

                if ($status->requiresReason() && blank($this->input('reason'))) {
                    $validator->errors()->add(
                        'reason',
                        'A reason is required when marking a learner as transferred out or dropped.',
                    );
                }
            },
        ];
    }
}
