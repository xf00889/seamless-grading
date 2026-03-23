<?php

namespace App\Http\Requests\Adviser;

use App\Enums\EnrollmentStatus;
use App\Models\Section;
use App\Models\SectionRoster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLearnerMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            ? ($this->user()?->can('manageLearnerMovementsAsAdviser', $section) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    EnrollmentStatus::Active->value,
                    EnrollmentStatus::TransferredOut->value,
                    EnrollmentStatus::Dropped->value,
                ]),
            ],
            'effective_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'search_filter' => ['nullable', 'string', 'max:120'],
            'status_filter' => [
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

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $section = $this->route('section');
                $sectionRoster = $this->route('section_roster');

                if (! $section instanceof Section || ! $sectionRoster instanceof SectionRoster || ! $this->filled('status')) {
                    return;
                }

                $status = EnrollmentStatus::from($this->string('status')->value());
                $effectiveDate = $this->date('effective_date');

                if ($status === EnrollmentStatus::TransferredOut && $effectiveDate === null) {
                    $validator->errors()->add('effective_date', 'An effective transfer-out date is required.');
                }

                if ($status === EnrollmentStatus::Dropped && blank($this->input('reason'))) {
                    $validator->errors()->add('reason', 'A reason is required when marking a learner as dropped.');
                }

                if ($effectiveDate !== null) {
                    if ($effectiveDate->lt($section->schoolYear->starts_on) || $effectiveDate->gt($section->schoolYear->ends_on)) {
                        $validator->errors()->add('effective_date', 'The effective date must fall within the selected school year.');
                    }

                    if ($sectionRoster->enrolled_on !== null && $effectiveDate->lt($sectionRoster->enrolled_on)) {
                        $validator->errors()->add('effective_date', 'The effective date cannot be earlier than the learner roster enrollment date.');
                    }
                }
            },
        ];
    }
}
