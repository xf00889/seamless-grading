<?php

namespace App\Http\Requests\TemplateManagement;

use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('template');

        return $template instanceof Template
            ? ($this->user()?->can('deactivate', $template) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [];
    }
}
