<?php

namespace App\Http\Requests\Adviser;

use App\Models\GradeSubmission;
use Illuminate\Foundation\Http\FormRequest;

class ReturnGradeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('grade_submission');

        return $submission instanceof GradeSubmission
            ? ($this->user()?->can('returnAsAdviser', $submission) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'remarks' => ['required', 'string', 'max:1000'],
        ];
    }
}
