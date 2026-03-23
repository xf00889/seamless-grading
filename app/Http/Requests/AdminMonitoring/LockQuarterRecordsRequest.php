<?php

namespace App\Http\Requests\AdminMonitoring;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

class LockQuarterRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            ? ($this->user()?->can('lockQuarterAsAdmin', $section) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [];
    }
}
