<?php

namespace App\Http\Requests\Sf1Imports;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmSf1ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('confirm', $this->route('import_batch')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
