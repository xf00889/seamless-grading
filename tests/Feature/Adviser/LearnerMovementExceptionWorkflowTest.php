<?php

namespace Tests\Feature\Adviser;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Enums\LearnerStatusAuditAction;
use App\Enums\RoleName;
use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\Learner;
use App\Models\LearnerStatusAuditLog;
use App\Models\QuarterlyGrade;
use App\Models\ReportCardRecord;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\Subject;
use App\Models\TeacherLoad;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerMovementExceptionWorkflowTest extends TestCase
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

    public function test_authorized_users_can_view_only_their_learner_movement_management_pages(): void
    {
        $context = $this->createContext();
        $otherAdviser = $this->createUserWithRole(RoleName::Adviser->value);
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.learner-movements.index', ['section' => $context['section']]))
            ->assertOk()
            ->assertSeeText('Transfer-Out and Dropout Exceptions')
            ->assertSeeText('Student, Alicia');

        $this->actingAs($otherAdviser)
            ->get(route('adviser.sections.learner-movements.index', ['section' => $context['section']]))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($context['admin'])
            ->get(route('admin.submission-monitoring.sections.learner-movements.index', ['section' => $context['section']]))
            ->assertOk()
            ->assertSeeText('Learner Movement Exceptions')
            ->assertSeeText('Student, Alicia');

        $this->actingAs($teacher)
            ->get(route('admin.submission-monitoring.sections.learner-movements.index', ['section' => $context['section']]))
            ->assertRedirect(route('access.denied'));
    }

    public function test_transfer_out_drop_correction_and_clear_are_validated_audited_and_invalidate_only_impacted_records(): void
    {
        $context = $this->createContext();
        $otherSection = Section::factory()->create([
            'school_year_id' => $context['schoolYear']->id,
            'grade_level_id' => $context['gradeLevel']->id,
            'adviser_id' => $this->createUserWithRole(RoleName::Adviser->value)->id,
            'name' => 'Section Unrelated',
        ]);
        $otherRoster = $this->createOfficialRoster($otherSection, $context['schoolYear'], 'Bruno', '900000003002');

        $submission = $this->createSubmission(
            $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH'),
            $context['gradingPeriods']['first'],
        );

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $context['sectionRoster']->id,
            'grade' => 89,
            'remarks' => 'Passed',
        ]);

        $historicalSf9 = $this->createReportCardRecord(
            $context['sectionRoster'],
            $context['section'],
            $context['gradingPeriods']['first'],
            $context['adviser'],
            true,
            TemplateDocumentType::Sf9,
        );
        $impactedSf9 = $this->createReportCardRecord(
            $context['sectionRoster'],
            $context['section'],
            $context['gradingPeriods']['third'],
            $context['adviser'],
            true,
            TemplateDocumentType::Sf9,
        );
        $sf10Record = $this->createReportCardRecord(
            $context['sectionRoster'],
            $context['section'],
            $context['gradingPeriods']['fourth'],
            $context['adviser'],
            true,
            TemplateDocumentType::Sf10,
        );
        $unrelatedSf10Record = $this->createReportCardRecord(
            $otherRoster,
            $otherSection,
            $context['gradingPeriods']['fourth'],
            $context['adviser'],
            true,
            TemplateDocumentType::Sf10,
        );

        $pageRoute = route('adviser.sections.learner-movements.index', ['section' => $context['section']]);

        $this->actingAs($context['adviser'])
            ->from($pageRoute)
            ->put(route('adviser.sections.learner-movements.update', [
                'section' => $context['section'],
                'section_roster' => $context['sectionRoster'],
            ]), [
                'status' => EnrollmentStatus::TransferredOut->value,
                'effective_date' => '',
                'reason' => '',
            ])
            ->assertRedirect($pageRoute)
            ->assertSessionHasErrors('effective_date');

        $this->actingAs($context['adviser'])
            ->from($pageRoute)
            ->put(route('adviser.sections.learner-movements.update', [
                'section' => $context['section'],
                'section_roster' => $context['sectionRoster'],
            ]), [
                'status' => EnrollmentStatus::Dropped->value,
                'effective_date' => '',
                'reason' => '',
            ])
            ->assertRedirect($pageRoute)
            ->assertSessionHasErrors('reason');

        $transferDate = '2026-01-15';

        $this->actingAs($context['adviser'])
            ->put(route('adviser.sections.learner-movements.update', [
                'section' => $context['section'],
                'section_roster' => $context['sectionRoster'],
            ]), [
                'status' => EnrollmentStatus::TransferredOut->value,
                'effective_date' => $transferDate,
                'reason' => 'Moved to another school district.',
            ])
            ->assertRedirect($pageRoute);

        $context['sectionRoster']->refresh();
        $context['sectionRoster']->learner->refresh();
        $historicalSf9->refresh();
        $impactedSf9->refresh();
        $sf10Record->refresh();
        $unrelatedSf10Record->refresh();

        $this->assertSame(EnrollmentStatus::TransferredOut, $context['sectionRoster']->enrollment_status);
        $this->assertSame($transferDate, $context['sectionRoster']->withdrawn_on?->toDateString());
        $this->assertSame('Moved to another school district.', $context['sectionRoster']->movement_reason);
        $this->assertSame($context['adviser']->id, $context['sectionRoster']->movement_recorded_by);
        $this->assertNotNull($context['sectionRoster']->movement_recorded_at);
        $this->assertSame(EnrollmentStatus::TransferredOut, $context['sectionRoster']->learner->enrollment_status);
        $this->assertSame($transferDate, $context['sectionRoster']->learner->transfer_effective_date?->toDateString());
        $this->assertDatabaseHas('quarterly_grades', [
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $context['sectionRoster']->id,
            'grade' => 89,
        ]);
        $this->assertTrue($historicalSf9->is_finalized);
        $this->assertFalse($impactedSf9->is_finalized);
        $this->assertFalse($sf10Record->is_finalized);
        $this->assertTrue($unrelatedSf10Record->is_finalized);

        $transferAudit = LearnerStatusAuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame(LearnerStatusAuditAction::TransferredOutMarked->value, $transferAudit->action->value);
        $this->assertSame($context['adviser']->id, $transferAudit->acted_by);
        $this->assertSame($transferDate, $transferAudit->metadata['effective_date']);
        $this->assertSame(1, $transferAudit->metadata['invalidated_sf9_record_count']);
        $this->assertSame(1, $transferAudit->metadata['invalidated_sf10_record_count']);

        $this->actingAs($context['admin'])
            ->put(route('admin.submission-monitoring.sections.learner-movements.update', [
                'section' => $context['section'],
                'section_roster' => $context['sectionRoster'],
            ]), [
                'status' => EnrollmentStatus::TransferredOut->value,
                'effective_date' => '2026-02-15',
                'reason' => 'Corrected transfer date after records review.',
            ])
            ->assertRedirect(route('admin.submission-monitoring.sections.learner-movements.index', [
                'section' => $context['section'],
            ]));

        $correctionAudit = LearnerStatusAuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame(LearnerStatusAuditAction::MovementCorrected->value, $correctionAudit->action->value);
        $this->assertSame('2026-02-15', $correctionAudit->metadata['effective_date']);

        $this->actingAs($context['admin'])
            ->put(route('admin.submission-monitoring.sections.learner-movements.update', [
                'section' => $context['section'],
                'section_roster' => $context['sectionRoster'],
            ]), [
                'status' => EnrollmentStatus::Active->value,
                'effective_date' => '',
                'reason' => '',
            ])
            ->assertRedirect(route('admin.submission-monitoring.sections.learner-movements.index', [
                'section' => $context['section'],
            ]));

        $context['sectionRoster']->refresh();

        $this->assertSame(EnrollmentStatus::Active, $context['sectionRoster']->enrollment_status);
        $this->assertNull($context['sectionRoster']->withdrawn_on);
        $this->assertNull($context['sectionRoster']->movement_reason);

        $clearAudit = LearnerStatusAuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame(LearnerStatusAuditAction::MovementCleared->value, $clearAudit->action->value);

        $this->actingAs($context['adviser'])
            ->put(route('adviser.sections.learner-movements.update', [
                'section' => $context['section'],
                'section_roster' => $context['sectionRoster'],
            ]), [
                'status' => EnrollmentStatus::Dropped->value,
                'effective_date' => '',
                'reason' => 'Stopped attending classes after the second quarter.',
            ])
            ->assertRedirect($pageRoute);

        $context['sectionRoster']->refresh();
        $context['sectionRoster']->learner->refresh();

        $this->assertSame(EnrollmentStatus::Dropped, $context['sectionRoster']->enrollment_status);
        $this->assertNotNull($context['sectionRoster']->withdrawn_on);
        $this->assertSame('Stopped attending classes after the second quarter.', $context['sectionRoster']->movement_reason);
        $this->assertSame(EnrollmentStatus::Dropped, $context['sectionRoster']->learner->enrollment_status);

        $dropAudit = LearnerStatusAuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame(LearnerStatusAuditAction::DroppedMarked->value, $dropAudit->action->value);
        $this->assertSame($context['adviser']->id, $dropAudit->acted_by);
        $this->assertSame('Stopped attending classes after the second quarter.', $dropAudit->metadata['movement_reason']);
    }

    private function createContext(): array
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 7']);
        $schoolYear = SchoolYear::factory()->create([
            'name' => '2025-2026',
            'starts_on' => '2025-06-01',
            'ends_on' => '2026-05-31',
            'is_active' => true,
        ]);
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
            'name' => 'Section Sampaguita',
        ]);
        $sectionRoster = $this->createOfficialRoster($section, $schoolYear, 'Alicia', '900000003001');

        $gradingPeriods = [
            'first' => GradingPeriod::factory()->create([
                'school_year_id' => $schoolYear->id,
                'quarter' => GradingQuarter::First,
                'starts_on' => '2025-06-10',
                'ends_on' => '2025-08-15',
                'is_open' => false,
            ]),
            'second' => GradingPeriod::factory()->create([
                'school_year_id' => $schoolYear->id,
                'quarter' => GradingQuarter::Second,
                'starts_on' => '2025-09-01',
                'ends_on' => '2025-11-15',
                'is_open' => false,
            ]),
            'third' => GradingPeriod::factory()->create([
                'school_year_id' => $schoolYear->id,
                'quarter' => GradingQuarter::Third,
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-02-15',
                'is_open' => false,
            ]),
            'fourth' => GradingPeriod::factory()->create([
                'school_year_id' => $schoolYear->id,
                'quarter' => GradingQuarter::Fourth,
                'starts_on' => '2026-03-01',
                'ends_on' => '2026-05-15',
                'is_open' => true,
            ]),
        ];

        return compact('admin', 'adviser', 'gradeLevel', 'schoolYear', 'section', 'sectionRoster', 'gradingPeriods');
    }

    private function createOfficialRoster(
        Section $section,
        SchoolYear $schoolYear,
        string $firstName,
        string $lrn,
    ): SectionRoster {
        $learner = Learner::factory()->create([
            'first_name' => $firstName,
            'last_name' => 'Student',
            'lrn' => $lrn,
            'enrollment_status' => EnrollmentStatus::Active,
        ]);

        return SectionRoster::factory()->create([
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'learner_id' => $learner->id,
            'import_batch_id' => null,
            'enrollment_status' => EnrollmentStatus::Active,
            'enrolled_on' => '2025-06-15',
            'is_official' => true,
        ]);
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

    private function createSubmission(TeacherLoad $teacherLoad, GradingPeriod $gradingPeriod): GradeSubmission
    {
        return GradeSubmission::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
            'grading_period_id' => $gradingPeriod->id,
            'status' => GradeSubmissionStatus::Approved,
            'submitted_by' => $teacherLoad->teacher_id,
            'submitted_at' => now()->subDay(),
            'approved_at' => now()->subHours(2),
        ]);
    }

    private function createReportCardRecord(
        SectionRoster $sectionRoster,
        Section $section,
        GradingPeriod $gradingPeriod,
        User $actor,
        bool $isFinalized,
        TemplateDocumentType $documentType,
    ): ReportCardRecord {
        return ReportCardRecord::factory()->create([
            'section_roster_id' => $sectionRoster->id,
            'section_id' => $section->id,
            'school_year_id' => $section->school_year_id,
            'learner_id' => $sectionRoster->learner_id,
            'grading_period_id' => $gradingPeriod->id,
            'template_id' => Template::factory()->state([
                'document_type' => $documentType,
                'grade_level_id' => $section->grade_level_id,
            ]),
            'document_type' => $documentType,
            'generated_by' => $actor->id,
            'is_finalized' => $isFinalized,
            'finalized_at' => $isFinalized ? now()->subHour() : null,
            'finalized_by' => $isFinalized ? $actor->id : null,
        ]);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
