<?php

namespace Tests\Feature\Teacher;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Enums\RoleName;
use App\Livewire\Teacher\GradeEntryPage;
use App\Models\ApprovalLog;
use App\Models\GradeChangeLog;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\Learner;
use App\Models\QuarterlyGrade;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\Subject;
use App\Models\TeacherLoad;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherGradeEntryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }

    public function test_teacher_can_only_open_owned_grade_entry_routes_and_mismatched_periods_404(): void
    {
        $context = $this->createGradeEntryContext();
        $otherTeacher = $this->createUserWithRole(RoleName::Teacher->value);
        $otherSchoolYear = SchoolYear::factory()->create();
        $otherPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $otherSchoolYear->id,
            'quarter' => GradingQuarter::Second,
        ]);

        $this->actingAs($context['teacher'])
            ->get(route('teacher.grade-entry.show', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertOk()
            ->assertSeeText('Quarterly Grade Entry');

        $this->actingAs($otherTeacher)
            ->get(route('teacher.grade-entry.show', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($context['teacher'])
            ->get(route('teacher.grade-entry.show', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $otherPeriod,
            ]))
            ->assertNotFound();
    }

    public function test_teacher_can_save_draft_for_official_roster_rows_only(): void
    {
        $context = $this->createGradeEntryContext();

        $officialActiveRoster = $this->createSectionRoster(
            $context['section'],
            $context['schoolYear'],
            EnrollmentStatus::Active,
        );
        $officialInactiveRoster = $this->createSectionRoster(
            $context['section'],
            $context['schoolYear'],
            EnrollmentStatus::Inactive,
        );
        $unofficialRoster = $this->createSectionRoster(
            $context['section'],
            $context['schoolYear'],
            EnrollmentStatus::Active,
            ['is_official' => false],
        );
        $otherSection = Section::factory()->create([
            'school_year_id' => $context['schoolYear']->id,
            'grade_level_id' => $context['gradeLevel']->id,
            'adviser_id' => $context['adviser']->id,
        ]);
        $otherSectionRoster = $this->createSectionRoster(
            $otherSection,
            $context['schoolYear'],
            EnrollmentStatus::Active,
        );

        Livewire::actingAs($context['teacher'])
            ->test(GradeEntryPage::class, [
                'teacherLoad' => $context['teacherLoad'],
                'gradingPeriod' => $context['gradingPeriod'],
            ])
            ->set("form.grades.{$officialActiveRoster->id}.grade", '88.50')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertSet('workflow.status.value', GradeSubmissionStatus::Draft->value);

        $submission = GradeSubmission::query()
            ->where('teacher_load_id', $context['teacherLoad']->id)
            ->where('grading_period_id', $context['gradingPeriod']->id)
            ->firstOrFail();

        $this->assertSame(GradeSubmissionStatus::Draft, $submission->status);
        $this->assertDatabaseHas('quarterly_grades', [
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $officialActiveRoster->id,
            'grade' => 88.5,
            'remarks' => 'Passed',
        ]);
        $this->assertDatabaseHas('quarterly_grades', [
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $officialInactiveRoster->id,
            'grade' => null,
            'remarks' => null,
        ]);
        $this->assertDatabaseHas('approval_logs', [
            'grade_submission_id' => $submission->id,
            'acted_by' => $context['teacher']->id,
            'action' => 'draft_saved',
        ]);
        $this->assertDatabaseMissing('quarterly_grades', [
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $unofficialRoster->id,
        ]);
        $this->assertDatabaseMissing('quarterly_grades', [
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $otherSectionRoster->id,
        ]);
    }

    public function test_submit_requires_complete_valid_grades_and_logs_submission(): void
    {
        $context = $this->createGradeEntryContext();
        $rosterOne = $this->createSectionRoster($context['section'], $context['schoolYear'], EnrollmentStatus::Active);
        $rosterTwo = $this->createSectionRoster($context['section'], $context['schoolYear'], EnrollmentStatus::Active);

        $component = Livewire::actingAs($context['teacher'])
            ->test(GradeEntryPage::class, [
                'teacherLoad' => $context['teacherLoad'],
                'gradingPeriod' => $context['gradingPeriod'],
            ])
            ->set("form.grades.{$rosterOne->id}.grade", '101')
            ->call('submitGrades')
            ->assertHasErrors(["form.grades.{$rosterOne->id}.grade"]);

        $component
            ->set("form.grades.{$rosterOne->id}.grade", '89')
            ->call('submitGrades')
            ->assertHasErrors(["form.grades.{$rosterTwo->id}.grade"]);

        $component
            ->set("form.grades.{$rosterTwo->id}.grade", '74.50')
            ->call('submitGrades')
            ->assertHasNoErrors()
            ->assertSet('workflow.status.value', GradeSubmissionStatus::Submitted->value);

        $submission = GradeSubmission::query()
            ->where('teacher_load_id', $context['teacherLoad']->id)
            ->where('grading_period_id', $context['gradingPeriod']->id)
            ->firstOrFail();

        $this->assertSame(GradeSubmissionStatus::Submitted, $submission->status);
        $this->assertSame($context['teacher']->id, $submission->submitted_by);
        $this->assertNotNull($submission->submitted_at);
        $this->assertDatabaseHas('quarterly_grades', [
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $rosterOne->id,
            'grade' => 89,
            'remarks' => 'Passed',
        ]);
        $this->assertDatabaseHas('quarterly_grades', [
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $rosterTwo->id,
            'grade' => 74.5,
            'remarks' => 'Failed',
        ]);
        $this->assertDatabaseHas('approval_logs', [
            'grade_submission_id' => $submission->id,
            'acted_by' => $context['teacher']->id,
            'action' => 'submitted',
        ]);
    }

    public function test_submitted_and_locked_submissions_cannot_be_edited(): void
    {
        $context = $this->createGradeEntryContext();
        $roster = $this->createSectionRoster($context['section'], $context['schoolYear'], EnrollmentStatus::Active);

        $submittedSubmission = GradeSubmission::factory()->create([
            'teacher_load_id' => $context['teacherLoad']->id,
            'grading_period_id' => $context['gradingPeriod']->id,
            'status' => GradeSubmissionStatus::Submitted,
            'submitted_by' => $context['teacher']->id,
            'submitted_at' => now()->subDay(),
        ]);

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submittedSubmission->id,
            'section_roster_id' => $roster->id,
            'grade' => 82,
            'remarks' => 'Passed',
        ]);

        Livewire::actingAs($context['teacher'])
            ->test(GradeEntryPage::class, [
                'teacherLoad' => $context['teacherLoad'],
                'gradingPeriod' => $context['gradingPeriod'],
            ])
            ->assertSee('already been submitted')
            ->set("form.grades.{$roster->id}.grade", '90')
            ->call('saveDraft')
            ->assertHasErrors(['form.record']);

        $submittedSubmission->update([
            'status' => GradeSubmissionStatus::Locked,
            'locked_at' => now(),
        ]);

        Livewire::actingAs($context['teacher'])
            ->test(GradeEntryPage::class, [
                'teacherLoad' => $context['teacherLoad'],
                'gradingPeriod' => $context['gradingPeriod'],
            ])
            ->assertSee('locked')
            ->set("form.grades.{$roster->id}.grade", '91')
            ->call('submitGrades')
            ->assertHasErrors(['form.record']);
    }

    public function test_returned_submission_preserves_adviser_remarks_and_allows_resubmission(): void
    {
        $context = $this->createGradeEntryContext();
        $roster = $this->createSectionRoster($context['section'], $context['schoolYear'], EnrollmentStatus::Active);

        $submission = GradeSubmission::factory()->create([
            'teacher_load_id' => $context['teacherLoad']->id,
            'grading_period_id' => $context['gradingPeriod']->id,
            'status' => GradeSubmissionStatus::Returned,
            'submitted_by' => $context['teacher']->id,
            'submitted_at' => now()->subDays(3),
            'returned_at' => now()->subDay(),
            'adviser_remarks' => 'Please correct the written works average.',
        ]);

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $roster->id,
            'grade' => 82,
            'remarks' => 'Passed',
        ]);

        Livewire::actingAs($context['teacher'])
            ->test(GradeEntryPage::class, [
                'teacherLoad' => $context['teacherLoad'],
                'gradingPeriod' => $context['gradingPeriod'],
            ])
            ->assertSee('Please correct the written works average.')
            ->set("form.grades.{$roster->id}.grade", '91')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertSet('workflow.status.value', GradeSubmissionStatus::Returned->value)
            ->call('submitGrades')
            ->assertHasNoErrors()
            ->assertSet('workflow.status.value', GradeSubmissionStatus::Submitted->value);

        $submission->refresh();

        $this->assertSame(GradeSubmissionStatus::Submitted, $submission->status);
        $this->assertSame('Please correct the written works average.', $submission->adviser_remarks);
        $this->assertNotNull($submission->returned_at);
        $this->assertDatabaseHas('quarterly_grades', [
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $roster->id,
            'grade' => 91,
            'remarks' => 'Passed',
        ]);
        $this->assertSame(
            ['draft_saved', 'submitted'],
            ApprovalLog::query()
                ->where('grade_submission_id', $submission->id)
                ->orderBy('id')
                ->get()
                ->map(fn (ApprovalLog $approvalLog): string => $approvalLog->action->value)
                ->all(),
        );
    }

    public function test_transfer_effective_date_blocks_grades_only_for_affected_and_later_periods(): void
    {
        $context = $this->createGradeEntryContext();

        $context['schoolYear']->update([
            'starts_on' => '2025-06-01',
            'ends_on' => '2026-05-31',
        ]);
        $context['gradingPeriod']->update([
            'starts_on' => '2025-06-10',
            'ends_on' => '2025-08-15',
        ]);

        $secondPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $context['schoolYear']->id,
            'quarter' => GradingQuarter::Second,
            'starts_on' => '2025-09-01',
            'ends_on' => '2025-11-15',
            'is_open' => true,
        ]);

        $roster = $this->createSectionRoster($context['section'], $context['schoolYear'], EnrollmentStatus::Active);

        $roster->forceFill([
            'enrollment_status' => EnrollmentStatus::TransferredOut,
            'withdrawn_on' => '2025-11-15',
            'movement_reason' => 'Transferred to another school after the first quarter.',
        ])->save();

        $roster->learner->forceFill([
            'enrollment_status' => EnrollmentStatus::TransferredOut,
            'transfer_effective_date' => '2025-11-15',
        ])->save();

        Livewire::actingAs($context['teacher'])
            ->test(GradeEntryPage::class, [
                'teacherLoad' => $context['teacherLoad'],
                'gradingPeriod' => $context['gradingPeriod'],
            ])
            ->set("form.grades.{$roster->id}.grade", '88')
            ->call('saveDraft')
            ->assertHasNoErrors();

        Livewire::actingAs($context['teacher'])
            ->test(GradeEntryPage::class, [
                'teacherLoad' => $context['teacherLoad'],
                'gradingPeriod' => $secondPeriod,
            ])
            ->set("form.grades.{$roster->id}.grade", '91')
            ->call('saveDraft')
            ->assertHasErrors(["form.grades.{$roster->id}.grade"]);
    }

    public function test_grade_change_logs_are_created_only_when_persisted_grade_values_change(): void
    {
        $context = $this->createGradeEntryContext();
        $roster = $this->createSectionRoster($context['section'], $context['schoolYear'], EnrollmentStatus::Active);

        $component = Livewire::actingAs($context['teacher'])
            ->test(GradeEntryPage::class, [
                'teacherLoad' => $context['teacherLoad'],
                'gradingPeriod' => $context['gradingPeriod'],
            ])
            ->set("form.grades.{$roster->id}.grade", '88.00')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $submission = GradeSubmission::query()
            ->where('teacher_load_id', $context['teacherLoad']->id)
            ->where('grading_period_id', $context['gradingPeriod']->id)
            ->firstOrFail();
        $quarterlyGrade = QuarterlyGrade::query()
            ->where('grade_submission_id', $submission->id)
            ->where('section_roster_id', $roster->id)
            ->firstOrFail();

        $this->assertDatabaseCount('grade_change_logs', 0);

        $component
            ->set("form.grades.{$roster->id}.grade", '88.00')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('grade_change_logs', 0);

        $component
            ->set("form.grades.{$roster->id}.grade", '91.50')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $component
            ->set("form.grades.{$roster->id}.grade", '91.50')
            ->call('submitGrades')
            ->assertHasNoErrors();

        $submission->update([
            'status' => GradeSubmissionStatus::Returned,
            'returned_at' => now(),
            'adviser_remarks' => 'Fix the learner total before resubmission.',
        ]);

        Livewire::actingAs($context['teacher'])
            ->test(GradeEntryPage::class, [
                'teacherLoad' => $context['teacherLoad'],
                'gradingPeriod' => $context['gradingPeriod'],
            ])
            ->set("form.grades.{$roster->id}.grade", '94.00')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $changeLogs = GradeChangeLog::query()
            ->where('quarterly_grade_id', $quarterlyGrade->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $changeLogs);
        $this->assertSame('88.00', $changeLogs[0]->previous_grade);
        $this->assertSame('91.50', $changeLogs[0]->new_grade);
        $this->assertSame($context['teacher']->id, $changeLogs[0]->changed_by);
        $this->assertSame('Teacher saved a grade draft.', $changeLogs[0]->reason);
        $this->assertSame('91.50', $changeLogs[1]->previous_grade);
        $this->assertSame('94.00', $changeLogs[1]->new_grade);
        $this->assertSame($context['teacher']->id, $changeLogs[1]->changed_by);
        $this->assertSame('Teacher saved corrections for a returned submission.', $changeLogs[1]->reason);
    }

    private function createGradeEntryContext(): array
    {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $gradeLevel = GradeLevel::factory()->create();
        $schoolYear = SchoolYear::factory()->create();
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $subject = Subject::factory()->create();
        $teacherLoad = TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'is_active' => true,
        ]);
        $gradingPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::First,
            'is_open' => true,
        ]);

        return compact(
            'teacher',
            'adviser',
            'gradeLevel',
            'schoolYear',
            'section',
            'subject',
            'teacherLoad',
            'gradingPeriod',
        );
    }

    private function createSectionRoster(
        Section $section,
        SchoolYear $schoolYear,
        EnrollmentStatus $status,
        array $attributes = [],
    ): SectionRoster {
        $learner = Learner::factory()->create([
            'enrollment_status' => $status,
        ]);

        return SectionRoster::factory()->create(array_merge([
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'learner_id' => $learner->id,
            'import_batch_id' => null,
            'enrollment_status' => $status,
            'is_official' => true,
        ], $attributes));
    }

    private function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
