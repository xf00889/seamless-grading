@php
    $startsOn = old('starts_on', optional($gradingPeriod->starts_on)->format('Y-m-d'));
    $endsOn = old('ends_on', optional($gradingPeriod->ends_on)->format('Y-m-d'));
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <div>
        <x-input-label for="school_year_id" value="School year" />
        <select id="school_year_id" name="school_year_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
            <option value="">Select a school year</option>
            @foreach ($schoolYears as $schoolYear)
                <option value="{{ $schoolYear->id }}" @selected((int) old('school_year_id', $gradingPeriod->school_year_id ?: $selectedSchoolYearId) === $schoolYear->id)>
                    {{ $schoolYear->name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('school_year_id')" />
    </div>

    <div>
        <x-input-label for="quarter" value="Quarter" />
        <select id="quarter" name="quarter" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
            <option value="">Select a quarter</option>
            @foreach ($quarters as $quarter)
                <option value="{{ $quarter->value }}" @selected((int) old('quarter', $gradingPeriod->quarter?->value) === $quarter->value)>
                    {{ $quarter->label() }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('quarter')" />
    </div>

    <div>
        <x-input-label for="starts_on" value="Start date" />
        <x-text-input id="starts_on" name="starts_on" type="date" class="mt-1 block w-full" :value="$startsOn" />
        <x-input-error class="mt-2" :messages="$errors->get('starts_on')" />
    </div>

    <div>
        <x-input-label for="ends_on" value="End date" />
        <x-text-input id="ends_on" name="ends_on" type="date" class="mt-1 block w-full" :value="$endsOn" />
        <x-input-error class="mt-2" :messages="$errors->get('ends_on')" />
    </div>
</div>
