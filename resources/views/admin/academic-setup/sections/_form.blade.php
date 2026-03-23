<div class="grid gap-6 lg:grid-cols-2">
    <div>
        <x-input-label for="school_year_id" value="School year" />
        <select id="school_year_id" name="school_year_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
            <option value="">Select a school year</option>
            @foreach ($schoolYears as $schoolYear)
                <option value="{{ $schoolYear->id }}" @selected((int) old('school_year_id', $section->school_year_id ?: $selectedSchoolYearId) === $schoolYear->id)>
                    {{ $schoolYear->name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('school_year_id')" />
    </div>

    <div>
        <x-input-label for="grade_level_id" value="Grade level" />
        <select id="grade_level_id" name="grade_level_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
            <option value="">Select a grade level</option>
            @foreach ($gradeLevels as $gradeLevel)
                <option value="{{ $gradeLevel->id }}" @selected((int) old('grade_level_id', $section->grade_level_id ?: $selectedGradeLevelId) === $gradeLevel->id)>
                    {{ $gradeLevel->name }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('grade_level_id')" />
    </div>

    <div>
        <x-input-label for="name" value="Section name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $section->name)" required />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="adviser_id" value="Adviser" />
        <select id="adviser_id" name="adviser_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400">
            <option value="">No adviser assigned</option>
            @foreach ($advisers as $adviser)
                <option value="{{ $adviser->id }}" @selected((int) old('adviser_id', $section->adviser_id) === $adviser->id)>
                    {{ $adviser->name }} · {{ $adviser->email }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('adviser_id')" />
    </div>
</div>
