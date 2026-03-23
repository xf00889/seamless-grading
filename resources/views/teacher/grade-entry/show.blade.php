<x-app-layout>
    <x-slot name="header">
        <x-page-header
            eyebrow="Teacher workspace"
            title="Quarterly Grade Entry"
            :description="$teacherLoad->subject->name.' · '.$teacherLoad->schoolYear->name.' · '.$teacherLoad->section->gradeLevel->name.' · '.$teacherLoad->section->name.' · '.$gradingPeriod->quarter->label()"
        >
            <x-slot name="actions">
                <a href="{{ route('teacher.grading-sheet.show', ['teacher_load' => $teacherLoad, 'grading_period' => $gradingPeriod]) }}" class="ui-link-button">
                    Preview/export sheet
                </a>
                <a href="{{ route('teacher.loads.show', $teacherLoad) }}" class="ui-link-button">
                    Back to learner list
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="page-stack">
        @include('teacher.partials.navigation')

        <livewire:teacher.grade-entry-page
            :teacher-load="$teacherLoad"
            :grading-period="$gradingPeriod"
        />
    </div>
</x-app-layout>
