<?php

namespace App\Http\Requests\UserManagement;

use App\Enums\RoleName;
use App\Models\Section;
use App\Models\TeacherLoad;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeacherLoadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('teacher_load') !== null
            ? $this->user()?->can('update', $this->route('teacher_load')) ?? false
            : $this->user()?->can('create', TeacherLoad::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'exists:users,id'],
            'school_year_id' => ['required', 'exists:school_years,id'],
            'section_id' => ['required', 'exists:sections,id'],
            'subject_id' => [
                'required',
                'exists:subjects,id',
                Rule::unique('teacher_loads')
                    ->where(fn ($query) => $query
                        ->where('teacher_id', $this->input('teacher_id'))
                        ->where('school_year_id', $this->input('school_year_id'))
                        ->where('section_id', $this->input('section_id')))
                    ->ignore($this->route('teacher_load')),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $teacher = User::query()->find($this->integer('teacher_id'));
                $section = Section::query()->find($this->integer('section_id'));
                $schoolYearId = $this->integer('school_year_id');

                if ($teacher !== null && ! $teacher->hasRole(RoleName::Teacher->value)) {
                    $validator->errors()->add('teacher_id', 'The selected user must have the teacher role.');
                }

                if ($teacher !== null && ! $teacher->is_active) {
                    $validator->errors()->add('teacher_id', 'The selected teacher must be active.');
                }

                if ($section !== null && $section->school_year_id !== $schoolYearId) {
                    $validator->errors()->add('section_id', 'The selected section must belong to the selected school year.');
                }
            },
        ];
    }
}
