<?php

namespace App\Http\Requests\Teacher;

use App\Enums\EnrollmentStatus;
use App\Models\TeacherLoad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionLearnerIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var TeacherLoad|null $teacherLoad */
        $teacherLoad = $this->route('teacher_load');

        return $teacherLoad !== null
            && ($this->user()?->can('viewLearners', $teacherLoad) ?? false);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'enrollment_status' => ['nullable', Rule::enum(EnrollmentStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
