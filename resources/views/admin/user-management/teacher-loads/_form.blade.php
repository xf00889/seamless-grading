@php
    $selectedSchoolYear = (int) old('school_year_id', $teacherLoad->school_year_id ?: ($selectedSchoolYearId ?? 0));
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <div>
        <x-input-label for="school_year_id" value="School year" />
        <select id="school_year_id" name="school_year_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
            <option value="">Select a school year</option>
            @foreach ($schoolYears as $schoolYear)
                <option value="{{ $schoolYear->id }}" @selected($selectedSchoolYear === $schoolYear->id)>
                    {{ $schoolYear->name }}{{ $schoolYear->is_active ? ' · Active' : '' }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('school_year_id')" />
    </div>

    <div>
        <x-input-label for="teacher_id" value="Teacher" />
        <select id="teacher_id" name="teacher_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
            <option value="">Select a teacher</option>
            @foreach ($teachers as $teacher)
                <option value="{{ $teacher->id }}" @selected((int) old('teacher_id', $teacherLoad->teacher_id) === $teacher->id)>
                    {{ $teacher->name }} · {{ $teacher->email }}{{ $teacher->is_active ? '' : ' · Inactive' }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('teacher_id')" />
    </div>

    <div>
        <x-input-label for="section_id" value="Section" />
        <select id="section_id" name="section_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
            <option value="">Select a section</option>
            @foreach ($sections as $section)
                <option value="{{ $section->id }}" @selected((int) old('section_id', $teacherLoad->section_id) === $section->id)>
                    {{ $section->schoolYear->name }} · {{ $section->gradeLevel->name }} · {{ $section->name }} · Adviser: {{ $section->adviser?->name ?? 'None' }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('section_id')" />
    </div>

    <div>
        <x-input-label for="subject_id" value="Subject" />
        <select id="subject_id" name="subject_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-400" required>
            <option value="">Select a subject</option>
            @foreach ($subjects as $subject)
                <option value="{{ $subject->id }}" @selected((int) old('subject_id', $teacherLoad->subject_id) === $subject->id)>
                    {{ $subject->code }} · {{ $subject->name }}{{ $subject->is_active ? '' : ' · Inactive' }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('subject_id')" />
    </div>
</div>
