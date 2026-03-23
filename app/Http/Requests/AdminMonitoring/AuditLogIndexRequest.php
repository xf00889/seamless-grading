<?php

namespace App\Http\Requests\AdminMonitoring;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuditLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAuditLogs', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => [
                'nullable',
                'string',
                Rule::in([
                    'draft_saved',
                    'uploaded',
                    'confirmed',
                    'submitted',
                    'returned',
                    'approved',
                    'locked',
                    'reopened',
                    'year_end_status_updated',
                    'mappings_updated',
                    'activated',
                    'deactivated',
                    'exported',
                    'finalized',
                ]),
            ],
            'module' => [
                'nullable',
                'string',
                Rule::in([
                    'sf1-imports',
                    'grading-workflow',
                    'templates',
                    'grading-sheet-exports',
                    'sf9-records',
                    'sf10-records',
                    'year-end-status',
                ]),
            ],
        ];
    }
}
