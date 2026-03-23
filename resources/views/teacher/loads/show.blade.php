<x-app-layout>
    <x-slot name="header">
        <x-page-header
            eyebrow="Teacher workspace"
            title="Official Learner List"
            :description="$teacherLoad->subject->name.' · '.$teacherLoad->schoolYear->name.' · '.$teacherLoad->section->gradeLevel->name.' · '.$teacherLoad->section->name"
        >
            <x-slot name="actions">
                <a href="{{ route('teacher.loads.index') }}" class="ui-link-button">
                    Back to my loads
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="page-stack">
        @include('admin.academic-setup.partials.flash')
        @include('teacher.partials.navigation')

        <div class="stats-grid">
            <x-stat-card label="Subject" :value="$teacherLoad->subject->name" :description="$teacherLoad->subject->code" icon="book" />
            <x-stat-card label="Section" :value="$teacherLoad->section->name" :description="$teacherLoad->section->gradeLevel->name" icon="section" />
            <x-stat-card label="Adviser" :value="$teacherLoad->section->adviser?->name ?? 'No adviser assigned'" :description="$teacherLoad->schoolYear->name" icon="users" />
            <x-card>
                <p class="stat-card__label">Load status</p>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <x-status-chip :state="$teacherLoad->is_active ? 'active' : 'inactive'">
                        {{ $teacherLoad->is_active ? 'Active' : 'Inactive' }}
                    </x-status-chip>
                    <x-status-chip :state="$teacherLoad->returned_submissions_count > 0 ? 'returned' : 'inactive'">
                        {{ $teacherLoad->returned_submissions_count }} returned
                    </x-status-chip>
                </div>
            </x-card>
        </div>

        <section class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
            @forelse ($gradingPeriods as $gradingPeriod)
                @php($submission = $gradingPeriod->gradeSubmissions->first())

                <x-section-panel>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="section-panel__eyebrow">Grading period</p>
                            <h2 class="mt-2 text-lg font-semibold text-slate-900">{{ $gradingPeriod->quarter->label() }}</h2>
                        </div>
                        <x-status-chip :state="$gradingPeriod->is_open ? 'active' : 'inactive'">
                            {{ $gradingPeriod->is_open ? 'Open' : 'Closed' }}
                        </x-status-chip>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-status-chip :tone="$submission?->status?->tone() ?? 'slate'">
                            {{ $submission?->status?->label() ?? 'Not started' }}
                        </x-status-chip>
                    </div>

                    @if ($submission?->adviser_remarks)
                        <p class="mt-4 text-sm leading-6 text-slate-600">{{ $submission->adviser_remarks }}</p>
                    @else
                        <p class="mt-4 text-sm leading-6 text-slate-500">Open the grade entry screen to save a draft or submit this quarter.</p>
                    @endif

                    <x-action-bar class="mt-5">
                        <a href="{{ route('teacher.grade-entry.show', ['teacher_load' => $teacherLoad, 'grading_period' => $gradingPeriod]) }}" class="ui-link-button">
                            {{ $submission?->status === \App\Enums\GradeSubmissionStatus::Returned ? 'Correct grades' : 'Enter grades' }}
                        </a>
                        <a href="{{ route('teacher.grading-sheet.show', ['teacher_load' => $teacherLoad, 'grading_period' => $gradingPeriod]) }}" class="ui-link-button">
                            Preview/export sheet
                        </a>
                    </x-action-bar>
                </x-section-panel>
            @empty
                <div class="lg:col-span-2 xl:col-span-4">
                    <x-empty-state title="No grading periods are available yet." description="Academic setup still needs to define grading periods for this school year before teachers can work in this load." />
                </div>
            @endforelse
        </section>

        <x-filter-bar title="Find learners" description="Search the official roster tied to this load and narrow the list by enrollment status.">
            <form method="GET" action="{{ route('teacher.loads.show', $teacherLoad) }}" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_auto]">
                <div>
                    <x-input-label for="search" value="Search learners" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search']" placeholder="LRN or learner name" />
                </div>

                <div>
                    <x-input-label for="enrollment_status" value="Enrollment status" />
                    <select id="enrollment_status" name="enrollment_status" class="ui-select mt-1">
                        <option value="">All official learners</option>
                        @foreach (\App\Enums\EnrollmentStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($filters['enrollment_status'] === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="action-bar items-end">
                    <x-primary-button>Filter</x-primary-button>
                    <a href="{{ route('teacher.loads.show', $teacherLoad) }}" class="ui-link-button">
                        Reset
                    </a>
                </div>
            </form>
        </x-filter-bar>

        <x-table-wrapper
            title="Official Section Roster"
            description="Only official roster records from the assigned section and school year are shown here."
            :count="$learners->total().' learner'.($learners->total() === 1 ? '' : 's')"
        >
            <x-slot name="actions">
                <x-status-chip tone="slate">{{ $teacherLoad->schoolYear->name }}</x-status-chip>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Learner</th>
                            <th>Sex</th>
                            <th>Status</th>
                            <th>Enrollment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($learners as $roster)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-900">
                                        {{ $roster->learner->last_name }}, {{ $roster->learner->first_name }}
                                        @if ($roster->learner->middle_name)
                                            {{ \Illuminate\Support\Str::limit($roster->learner->middle_name, 1, '.') }}
                                        @endif
                                    </p>
                                    <p class="table-support">LRN {{ $roster->learner->lrn }}</p>
                                </td>
                                <td class="text-slate-600">{{ strtoupper($roster->learner->sex->value) }}</td>
                                <td>
                                    <x-status-chip :tone="$roster->enrollment_status->tone()">
                                        {{ $roster->enrollment_status->label() }}
                                    </x-status-chip>
                                </td>
                                <td class="text-slate-600">
                                    <p>{{ $roster->enrolled_on?->format('M d, Y') ?? 'Not recorded' }}</p>
                                    <p class="table-support">
                                        {{ $roster->withdrawn_on?->format('M d, Y') ?? 'Still enrolled' }}
                                    </p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <x-empty-state title="No official learners matched these filters." description="Try a different learner keyword or switch the enrollment-status filter." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-slot name="footer">
                {{ $learners->links() }}
            </x-slot>
        </x-table-wrapper>
    </div>
</x-app-layout>
