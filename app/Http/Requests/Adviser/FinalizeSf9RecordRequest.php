<?php

namespace App\Http\Requests\Adviser;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeSf9RecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $section = $this->route('section');

        return $section instanceof Section
            ? ($this->user()?->can('finalizeSf9AsAdviser', $section) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [];
    }
}
