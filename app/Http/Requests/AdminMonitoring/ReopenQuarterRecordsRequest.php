<?php

namespace App\Http\Requests\AdminMonitoring;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

class ReopenQuarterRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            ? ($this->user()?->can('reopenQuarterAsAdmin', $section) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
