<?php

namespace Tests\Feature\Adviser;

use App\Enums\ApprovalAction;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Enums\RoleName;
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
use Tests\TestCase;

class AdviserReviewWorkflowTest extends TestCase
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

    public function test_dashboard_and_section_index_only_show_owned_sections(): void
    {
        $context = $this->createAdviserContext();
        $otherAdviser = $this->createUserWithRole(RoleName::Adviser->value);
        $otherSection = Section::factory()->create([
            'school_year_id' => $context['schoolYear']->id,
            'grade_level_id' => $context['gradeLevel']->id,
            'adviser_id' => $otherAdviser->id,
            'name' => 'Section B',
        ]);

        $approvedLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $returnedLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Science', 'SCI');
        $otherLoad = $this->createTeacherLoad($otherSection, $context['schoolYear'], 'English', 'ENG');

        $this->createSubmission($approvedLoad, $context['gradingPeriod'], GradeSubmissionStatus::Approved);
        $this->createSubmission($returnedLoad, $context['gradingPeriod'], GradeSubmissionStatus::Returned, 'Please correct the class standing total.');
        $this->createSubmission($otherLoad, $context['gradingPeriod'], GradeSubmissionStatus::Approved);

        $this->actingAs($context['adviser'])
            ->get(route('adviser.dashboard', [
                'school_year_id' => $context['schoolYear']->id,
                'grading_period_id' => $context['gradingPeriod']->id,
            ]))
            ->assertOk()
            ->assertSeeText($context['section']->name)
            ->assertDontSeeText($otherSection->name)
            ->assertSeeText('Missing submissions')
            ->assertSeeText('Returned submissions');

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.index', [
                'school_year_id' => $context['schoolYear']->id,
                'grading_period_id' => $context['gradingPeriod']->id,
            ]))
            ->assertOk()
            ->assertSeeText($context['section']->name)
            ->assertDontSeeText($otherSection->name);
    }

    public function test_tracker_surfaces_missing_subject_submissions_for_owned_section(): void
    {
        $context = $this->createAdviserContext();
        $submittedLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $missingLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Science', 'SCI');

        $this->createSubmission($submittedLoad, $context['gradingPeriod'], GradeSubmissionStatus::Submitted);

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.tracker', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertOk()
            ->assertSeeText('Mathematics')
            ->assertSeeText('Science')
            ->assertSeeText('Submitted')
            ->assertSeeText('Missing')
            ->assertSeeText('Science is still missing');
    }

    public function test_review_routes_require_owned_section_and_matching_period_scope(): void
    {
        $context = $this->createAdviserContext();
        $otherAdviser = $this->createUserWithRole(RoleName::Adviser->value);
        $otherSchoolYear = SchoolYear::factory()->create();
        $otherPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $otherSchoolYear->id,
            'quarter' => GradingQuarter::Second,
        ]);

        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submission = $this->createSubmission($load, $context['gradingPeriod'], GradeSubmissionStatus::Submitted);

        $this->actingAs($otherAdviser)
            ->get(route('adviser.sections.tracker', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($otherAdviser)
            ->get(route('adviser.sections.submissions.show', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'grade_submission' => $submission,
            ]))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($otherAdviser)
            ->post(route('adviser.sections.submissions.approve', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'grade_submission' => $submission,
            ]))
            ->assertForbidden();

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.submissions.show', [
                'section' => $context['section'],
                'grading_period' => $otherPeriod,
                'grade_submission' => $submission,
            ]))
            ->assertNotFound();
    }

    public function test_return_requires_remarks_and_logs_explicit_transition(): void
    {
        $context = $this->createAdviserContext();
        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submission = $this->createSubmission($load, $context['gradingPeriod'], GradeSubmissionStatus::Submitted);

        $this->createOfficialRosterWithGrade($context['section'], $context['schoolYear'], $submission, 'Learner One', '900000000001', 88);

        $this->actingAs($context['adviser'])
            ->from(route('adviser.sections.submissions.show', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'grade_submission' => $submission,
            ]))
            ->post(route('adviser.sections.submissions.return', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'grade_submission' => $submission,
            ]), [
                'remarks' => '',
            ])
            ->assertSessionHasErrors('remarks');

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.submissions.return', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'grade_submission' => $submission,
            ]), [
                'remarks' => 'Please review the learner averages before resubmitting.',
            ])
            ->assertRedirect(route('adviser.sections.submissions.show', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'grade_submission' => $submission,
            ]));

        $submission->refresh();

        $this->assertSame(GradeSubmissionStatus::Returned, $submission->status);
        $this->assertSame('Please review the learner averages before resubmitting.', $submission->adviser_remarks);
        $this->assertNotNull($submission->returned_at);
        $this->assertDatabaseHas('approval_logs', [
            'grade_submission_id' => $submission->id,
            'acted_by' => $context['adviser']->id,
            'action' => ApprovalAction::Returned->value,
            'remarks' => 'Please review the learner averages before resubmitting.',
        ]);
    }

    public function test_approve_updates_workflow_state_and_audits_the_action(): void
    {
        $context = $this->createAdviserContext();
        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submission = $this->createSubmission($load, $context['gradingPeriod'], GradeSubmissionStatus::Submitted);
        $this->createOfficialRosterWithGrade(
            $context['section'],
            $context['schoolYear'],
            $submission,
            'Learner One',
            '900000000002',
            89,
        );

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.submissions.approve', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'grade_submission' => $submission,
            ]))
            ->assertRedirect(route('adviser.sections.submissions.show', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'grade_submission' => $submission,
            ]));

        $submission->refresh();

        $this->assertSame(GradeSubmissionStatus::Approved, $submission->status);
        $this->assertNotNull($submission->approved_at);
        $this->assertDatabaseHas('approval_logs', [
            'grade_submission_id' => $submission->id,
            'acted_by' => $context['adviser']->id,
            'action' => ApprovalAction::Approved->value,
        ]);
    }

    public function test_approve_is_blocked_when_a_saved_grade_row_is_no_longer_grade_eligible_for_the_period(): void
    {
        $context = $this->createAdviserContext();
        $context['schoolYear']->update([
            'starts_on' => '2025-06-01',
            'ends_on' => '2026-05-31',
        ]);
        $context['gradingPeriod']->update([
            'starts_on' => '2025-09-01',
            'ends_on' => '2025-11-15',
        ]);

        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submission = $this->createSubmission($load, $context['gradingPeriod'], GradeSubmissionStatus::Submitted);
        $sectionRoster = $this->createOfficialRosterWithGrade(
            $context['section'],
            $context['schoolYear'],
            $submission,
            'Learner Ineligible',
            '900000000021',
            88,
        );

        $sectionRoster->forceFill([
            'enrollment_status' => EnrollmentStatus::TransferredOut,
            'withdrawn_on' => '2025-11-15',
            'movement_reason' => 'Transferred before the quarter closed.',
        ])->save();

        $sectionRoster->learner->forceFill([
            'enrollment_status' => EnrollmentStatus::TransferredOut,
            'transfer_effective_date' => '2025-11-15',
        ])->save();

        $this->actingAs($context['adviser'])
            ->from(route('adviser.sections.submissions.show', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'grade_submission' => $submission,
            ]))
            ->post(route('adviser.sections.submissions.approve', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'grade_submission' => $submission,
            ]))
            ->assertSessionHasErrors(["form.grades.{$sectionRoster->id}.grade"]);

        $submission->refresh();

        $this->assertSame(GradeSubmissionStatus::Submitted, $submission->status);
        $this->assertNull($submission->approved_at);
    }

    public function test_consolidation_uses_only_approved_submissions_and_official_roster_learners(): void
    {
        $context = $this->createAdviserContext();
        $approvedLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submittedLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Science', 'SCI');

        $approvedSubmission = $this->createSubmission($approvedLoad, $context['gradingPeriod'], GradeSubmissionStatus::Approved);
        $submittedSubmission = $this->createSubmission($submittedLoad, $context['gradingPeriod'], GradeSubmissionStatus::Submitted);

        $officialRoster = $this->createOfficialRosterWithGrade(
            $context['section'],
            $context['schoolYear'],
            $approvedSubmission,
            'Learner Approved',
            '900000000010',
            91,
        );

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submittedSubmission->id,
            'section_roster_id' => $officialRoster->id,
            'grade' => 85,
            'remarks' => 'Passed',
        ]);

        $unofficialLearner = Learner::factory()->create([
            'first_name' => 'Unofficial',
            'last_name' => 'Learner',
            'lrn' => '900000000099',
        ]);

        SectionRoster::factory()->create([
            'section_id' => $context['section']->id,
            'school_year_id' => $context['schoolYear']->id,
            'learner_id' => $unofficialLearner->id,
            'import_batch_id' => null,
            'enrollment_status' => EnrollmentStatus::Active,
            'is_official' => false,
        ]);

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.consolidation.learners', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertOk()
            ->assertSeeText('Mathematics')
            ->assertSeeText('Learner Approved')
            ->assertDontSeeText('85.00')
            ->assertDontSeeText('Unofficial');

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.consolidation.subjects', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertOk()
            ->assertSeeText('Mathematics')
            ->assertSeeText('91.00')
            ->assertDontSeeText('85.00')
            ->assertDontSeeText('Unofficial');
    }

    public function test_section_is_not_ready_until_all_active_loads_are_approved(): void
    {
        $context = $this->createAdviserContext();
        $approvedLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submittedLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Science', 'SCI');

        $approvedSubmission = $this->createSubmission($approvedLoad, $context['gradingPeriod'], GradeSubmissionStatus::Approved);
        $submittedSubmission = $this->createSubmission($submittedLoad, $context['gradingPeriod'], GradeSubmissionStatus::Submitted);
        $sectionRoster = $this->createOfficialRosterWithGrade(
            $context['section'],
            $context['schoolYear'],
            $approvedSubmission,
            'Learner Ready',
            '900000000031',
            91,
        );
        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submittedSubmission->id,
            'section_roster_id' => $sectionRoster->id,
            'grade' => 90,
            'remarks' => 'Passed',
        ]);

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.tracker', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertOk()
            ->assertSeeText('Not ready')
            ->assertSeeText('Science is submitted');

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.submissions.approve', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'grade_submission' => $submittedSubmission,
            ]))
            ->assertRedirect();

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.tracker', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertOk()
            ->assertSeeText('Ready for finalization');
    }

    private function createAdviserContext(): array
    {
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $gradeLevel = GradeLevel::factory()->create([
            'name' => 'Grade 7',
            'code' => 'G7',
        ]);
        $schoolYear = SchoolYear::factory()->create();
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
            'name' => 'Section A',
        ]);
        $gradingPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::First,
            'is_open' => true,
        ]);

        return compact('adviser', 'gradeLevel', 'schoolYear', 'section', 'gradingPeriod');
    }

    private function createTeacherLoad(
        Section $section,
        SchoolYear $schoolYear,
        string $subjectName,
        string $subjectCode,
    ): TeacherLoad {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $subject = Subject::factory()->create([
            'name' => $subjectName,
            'code' => $subjectCode,
        ]);

        return TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'is_active' => true,
        ]);
    }

    private function createSubmission(
        TeacherLoad $teacherLoad,
        GradingPeriod $gradingPeriod,
        GradeSubmissionStatus $status,
        ?string $adviserRemarks = null,
    ): GradeSubmission {
        return GradeSubmission::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
            'grading_period_id' => $gradingPeriod->id,
            'status' => $status,
            'submitted_by' => $teacherLoad->teacher_id,
            'submitted_at' => $status === GradeSubmissionStatus::Draft ? null : now()->subHour(),
            'returned_at' => $status === GradeSubmissionStatus::Returned ? now()->subMinutes(30) : null,
            'approved_at' => $status === GradeSubmissionStatus::Approved ? now()->subMinutes(15) : null,
            'locked_at' => $status === GradeSubmissionStatus::Locked ? now() : null,
            'adviser_remarks' => $adviserRemarks,
        ]);
    }

    private function createOfficialRosterWithGrade(
        Section $section,
        SchoolYear $schoolYear,
        GradeSubmission $submission,
        string $firstName,
        string $lrn,
        float $grade,
    ): SectionRoster {
        $learner = Learner::factory()->create([
            'first_name' => $firstName,
            'last_name' => 'Student',
            'lrn' => $lrn,
            'enrollment_status' => EnrollmentStatus::Active,
        ]);

        $sectionRoster = SectionRoster::factory()->create([
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'learner_id' => $learner->id,
            'import_batch_id' => null,
            'enrollment_status' => EnrollmentStatus::Active,
            'is_official' => true,
        ]);

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $sectionRoster->id,
            'grade' => $grade,
            'remarks' => 'Passed',
        ]);

        return $sectionRoster;
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
