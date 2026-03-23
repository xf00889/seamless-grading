<?php

namespace App\Http\Requests\AcademicSetup;

use App\Enums\GradingQuarter;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class GradingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('grading_period') !== null
            ? $this->user()?->can('update', $this->route('grading_period')) ?? false
            : $this->user()?->can('create', GradingPeriod::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'school_year_id' => ['required', 'exists:school_years,id'],
            'quarter' => [
                'required',
                new Enum(GradingQuarter::class),
                Rule::unique('grading_periods', 'quarter')
                    ->where(fn ($query) => $query->where('school_year_id', $this->input('school_year_id')))
                    ->ignore($this->route('grading_period')),
            ],
            'starts_on' => ['nullable', 'date', 'required_with:ends_on'],
            'ends_on' => ['nullable', 'date', 'required_with:starts_on', 'after_or_equal:starts_on'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $validator->errors()->has('school_year_id')
                    || $validator->errors()->has('quarter')
                    || $validator->errors()->has('starts_on')
                    || $validator->errors()->has('ends_on')
                ) {
                    return;
                }

                $schoolYear = SchoolYear::query()->find($this->input('school_year_id'));

                if ($schoolYear === null) {
                    return;
                }

                if ($this->filled('starts_on') && $this->date('starts_on')->lt($schoolYear->starts_on)) {
                    $validator->errors()->add('starts_on', 'The grading period start date must fall within the selected school year.');
                }

                if ($this->filled('ends_on') && $this->date('ends_on')->gt($schoolYear->ends_on)) {
                    $validator->errors()->add('ends_on', 'The grading period end date must fall within the selected school year.');
                }

                $quarter = $this->enum('quarter', GradingQuarter::class);

                if ($quarter === null) {
                    return;
                }

                $existingQuarters = GradingPeriod::query()
                    ->where('school_year_id', $schoolYear->id)
                    ->when(
                        $this->route('grading_period') !== null,
                        fn ($query) => $query->whereKeyNot($this->route('grading_period')->getKey()),
                    )
                    ->pluck('quarter')
                    ->map(fn ($value) => $value instanceof GradingQuarter ? $value->value : (int) $value)
                    ->all();

                $requiredPrecedingQuarters = $quarter->value > 1
                    ? range(1, $quarter->value - 1)
                    : [];

                $missingPrecedingQuarter = collect($requiredPrecedingQuarters)
                    ->first(fn (int $value): bool => ! in_array($value, $existingQuarters, true));

                if ($missingPrecedingQuarter !== null) {
                    $validator->errors()->add(
                        'quarter',
                        sprintf('Create Q%d before setting up %s for this school year.', $missingPrecedingQuarter, $quarter->label()),
                    );
                }
            },
        ];
    }
}
