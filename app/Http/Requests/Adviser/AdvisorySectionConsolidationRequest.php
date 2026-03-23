<?php

namespace App\Http\Requests\Adviser;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

class AdvisorySectionConsolidationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            ? ($this->user()?->can('viewAsAdviser', $section) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
