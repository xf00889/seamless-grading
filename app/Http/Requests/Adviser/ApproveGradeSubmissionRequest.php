<?php

namespace App\Http\Requests\Adviser;

use App\Models\GradeSubmission;
use Illuminate\Foundation\Http\FormRequest;

class ApproveGradeSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('grade_submission');

        return $submission instanceof GradeSubmission
            ? ($this->user()?->can('approveAsAdviser', $submission) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [];
    }
}
