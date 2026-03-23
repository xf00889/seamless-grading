<?php

namespace App\Http\Requests\Teacher;

use App\Models\TeacherLoad;
use Illuminate\Foundation\Http\FormRequest;

class ExportGradingSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $teacherLoad = $this->route('teacher_load');

        return $teacherLoad instanceof TeacherLoad
            ? ($this->user()?->can('exportGradingSheet', $teacherLoad) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [];
    }
}
