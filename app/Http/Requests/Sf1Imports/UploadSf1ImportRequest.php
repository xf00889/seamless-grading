<?php

namespace App\Http\Requests\Sf1Imports;

use App\Models\ImportBatch;
use Illuminate\Foundation\Http\FormRequest;

class UploadSf1ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ImportBatch::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', 'exists:sections,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }
}
