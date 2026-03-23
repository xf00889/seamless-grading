<?php

namespace Tests\Feature\Admin\SubmissionMonitoring;

use App\Enums\ApprovalAction;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Enums\GradingSheetExportAuditAction;
use App\Enums\ReportCardRecordAuditAction;
use App\Enums\RoleName;
use App\Enums\TemplateAuditAction;
use App\Enums\TemplateDocumentType;
use App\Livewire\Teacher\GradeEntryPage;
use App\Models\ApprovalLog;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\GradingSheetExport;
use App\Models\GradingSheetExportAuditLog;
use App\Models\ImportBatch;
use App\Models\Learner;
use App\Models\LearnerStatusAuditLog;
use App\Models\QuarterlyGrade;
use App\Models\ReportCardRecord;
use App\Models\ReportCardRecordAuditLog;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\Subject;
use App\Models\TeacherLoad;
use App\Models\Template;
use App\Models\TemplateAuditLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionMonitoringTest extends TestCase
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

    public function test_only_admin_can_access_monitoring_audit_and_lock_routes(): void
    {
        $context = $this->createReadyQuarterContext();
        $lockedContext = $this->lockQuarterContext($context);

        $this->actingAs($context['admin'])
            ->get(route('admin.submission-monitoring'))
            ->assertOk()
            ->assertSeeText('Monitoring');

        $this->actingAs($context['admin'])
            ->get(route('admin.submission-monitoring.audit'))
            ->assertOk()
            ->assertSeeText('Audit Log');

        foreach ([RoleName::Teacher, RoleName::Adviser, RoleName::Registrar] as $role) {
            $user = $this->createUserWithRole($role->value);

            $this->actingAs($user)
                ->get(route('admin.submission-monitoring'))
                ->assertRedirect(route('access.denied'));

            $this->actingAs($user)
                ->get(route('admin.submission-monitoring.audit'))
                ->assertRedirect(route('access.denied'));

            $this->actingAs($user)
                ->post(route('admin.submission-monitoring.sections.lock', [
                    'section' => $context['section'],
                    'grading_period' => $context['gradingPeriod'],
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.submission-monitoring.sections.reopen', [
                    'section' => $lockedContext['section'],
                    'grading_period' => $lockedContext['gradingPeriod'],
                ]), [
                    'reason' => 'Recheck the quarter totals.',
                ])
                ->assertForbidden();
        }
    }

    public function test_monitoring_summary_uses_dashboard_metric_cards(): void
    {
        $context = $this->createReadyQuarterContext();

        $this->actingAs($context['admin'])
            ->get(route('admin.submission-monitoring'))
            ->assertOk()
            ->assertSee('submission-monitoring__metrics', false)
            ->assertSeeText('Missing submissions')
            ->assertSeeText('Completed sections')
            ->assertSee('studio-metric studio-metric--rose', false)
            ->assertSee('studio-metric studio-metric--sky', false)
            ->assertSee('studio-metric studio-metric--amber', false)
            ->assertSee('studio-metric studio-metric--teal', false)
            ->assertSee('studio-metric studio-metric--emerald', false)
            ->assertDontSee('stat-card', false);
    }

    public function test_monitoring_filters_by_school_year_grading_period_section_adviser_teacher_and_status(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);

        $targetSchoolYear = SchoolYear::factory()->create([
            'name' => 'SY 2026-2027',
            'is_active' => true,
        ]);
        $otherSchoolYear = SchoolYear::factory()->create([
            'name' => 'SY 2025-2026',
            'is_active' => false,
        ]);

        $targetPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $targetSchoolYear->id,
            'quarter' => GradingQuarter::First,
            'ends_on' => now()->addWeek()->toDateString(),
        ]);
        $otherPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $otherSchoolYear->id,
            'quarter' => GradingQuarter::First,
            'ends_on' => now()->addWeek()->toDateString(),
        ]);

        $targetAdviser = $this->createUserWithRole(RoleName::Adviser->value, ['name' => 'Adviser Filter Target']);
        $otherAdviser = $this->createUserWithRole(RoleName::Adviser->value, ['name' => 'Adviser Filter Other']);
        $targetTeacher = $this->createUserWithRole(RoleName::Teacher->value, ['name' => 'Teacher Filter Target']);
        $otherTeacher = $this->createUserWithRole(RoleName::Teacher->value, ['name' => 'Teacher Filter Other']);

        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 7']);

        $targetSection = Section::factory()->create([
            'school_year_id' => $targetSchoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $targetAdviser->id,
            'name' => 'Section Filter Target',
        ]);
        $otherSection = Section::factory()->create([
            'school_year_id' => $targetSchoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $otherAdviser->id,
            'name' => 'Section Filter Other',
        ]);

        $targetLoad = $this->createTeacherLoad($targetSection, $targetSchoolYear, $targetTeacher, 'Target Subject Alpha', 'TSA');
        $otherTeacherLoad = $this->createTeacherLoad($targetSection, $targetSchoolYear, $otherTeacher, 'Other Subject Beta', 'OSB');
        $otherSectionLoad = $this->createTeacherLoad($otherSection, $targetSchoolYear, $otherTeacher, 'Other Subject Gamma', 'OSG');
        $otherYearSection = Section::factory()->create([
            'school_year_id' => $otherSchoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $otherAdviser->id,
            'name' => 'Section Filter Legacy',
        ]);
        $otherYearLoad = $this->createTeacherLoad($otherYearSection, $otherSchoolYear, $otherTeacher, 'Legacy Subject Delta', 'LSD');

        $this->createSubmission($targetLoad, $targetPeriod, GradeSubmissionStatus::Returned, submittedAt: now()->subDay());
        $this->createSubmission($otherTeacherLoad, $targetPeriod, GradeSubmissionStatus::Approved, submittedAt: now()->subDay());
        $this->createSubmission($otherSectionLoad, $targetPeriod, GradeSubmissionStatus::Returned, submittedAt: now()->subDay());
        $this->createSubmission($otherYearLoad, $otherPeriod, GradeSubmissionStatus::Returned, submittedAt: now()->subDay());

        $this->actingAs($admin)
            ->get(route('admin.submission-monitoring', [
                'school_year_id' => $targetSchoolYear->id,
                'grading_period_id' => $targetPeriod->id,
                'section_id' => $targetSection->id,
                'adviser_id' => $targetAdviser->id,
                'teacher_id' => $targetTeacher->id,
                'status' => GradeSubmissionStatus::Returned->value,
            ]))
            ->assertOk()
            ->assertSeeText('Target Subject Alpha')
            ->assertDontSeeText('Other Subject Beta')
            ->assertDontSeeText('Other Subject Gamma')
            ->assertDontSeeText('Legacy Subject Delta');
    }

    public function test_monitoring_surfaces_late_submissions_based_on_grading_period_deadline(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $schoolYear = SchoolYear::factory()->create(['is_active' => true]);
        $gradingPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::First,
            'ends_on' => now()->subDay()->toDateString(),
        ]);
        $gradeLevel = GradeLevel::factory()->create();
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
            'name' => 'Late Section',
        ]);

        $lateSubmittedLoad = $this->createTeacherLoad($section, $schoolYear, $teacher, 'Late Submitted Subject', 'LSS');
        $missingLoad = $this->createTeacherLoad($section, $schoolYear, $this->createUserWithRole(RoleName::Teacher->value), 'Late Missing Subject', 'LMS');
        $onTimeLoad = $this->createTeacherLoad($section, $schoolYear, $this->createUserWithRole(RoleName::Teacher->value), 'On Time Subject', 'OTS');

        $this->createSubmission($lateSubmittedLoad, $gradingPeriod, GradeSubmissionStatus::Submitted, submittedAt: now());
        $this->createSubmission($onTimeLoad, $gradingPeriod, GradeSubmissionStatus::Submitted, submittedAt: now()->subDays(2));

        $this->actingAs($admin)
            ->get(route('admin.submission-monitoring', [
                'school_year_id' => $schoolYear->id,
                'grading_period_id' => $gradingPeriod->id,
            ]))
            ->assertOk()
            ->assertSee('>Late</span>', false)
            ->assertSeeText('Late submissions')
            ->assertSeeText('2 late submission item(s)')
            ->assertSeeText('The latest teacher submission happened after the grading-period deadline.')
            ->assertSeeText('No formal submission exists after the grading-period deadline.')
            ->assertDontSeeText('Late items pending');
    }

    public function test_monitoring_shows_ready_and_in_progress_sections(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $readyContext = $this->createReadyQuarterContext([
            'section_name' => 'Ready Section',
        ]);
        $inProgressAdviser = $this->createUserWithRole(RoleName::Adviser->value);
        $inProgressTeacher = $this->createUserWithRole(RoleName::Teacher->value);
        $inProgressSection = Section::factory()->create([
            'school_year_id' => $readyContext['schoolYear']->id,
            'grade_level_id' => $readyContext['gradeLevel']->id,
            'adviser_id' => $inProgressAdviser->id,
            'name' => 'In Progress Section',
        ]);
        $inProgressLoad = $this->createTeacherLoad($inProgressSection, $readyContext['schoolYear'], $inProgressTeacher, 'In Progress Subject', 'IPS');

        $this->createSubmission($inProgressLoad, $readyContext['gradingPeriod'], GradeSubmissionStatus::Submitted, submittedAt: now()->subHour());

        $this->actingAs($admin)
            ->get(route('admin.submission-monitoring', [
                'school_year_id' => $readyContext['schoolYear']->id,
                'grading_period_id' => $readyContext['gradingPeriod']->id,
            ]))
            ->assertOk()
            ->assertSeeText('Ready Section')
            ->assertSee('>Ready</span>', false)
            ->assertSeeText('In Progress Section')
            ->assertSee('>Open</span>', false)
            ->assertDontSeeText('Ready to lock')
            ->assertDontSeeText('In progress');
    }

    public function test_monitoring_excludes_post_exception_rosters_from_sf9_completion_requirements(): void
    {
        $context = $this->createReadyQuarterContext([
            'section_name' => 'Movement Ready Section',
        ]);

        $context['schoolYear']->update([
            'starts_on' => '2025-06-01',
            'ends_on' => '2026-05-31',
        ]);
        $context['gradingPeriod']->update([
            'starts_on' => '2025-09-01',
            'ends_on' => '2025-11-15',
        ]);

        $movedRoster = $this->createOfficialRoster(
            $context['section'],
            $context['schoolYear'],
            'Moved',
            '900000000211',
        );

        $movedRoster->forceFill([
            'enrollment_status' => EnrollmentStatus::TransferredOut,
            'withdrawn_on' => '2025-11-15',
            'movement_reason' => 'Transferred before the grading-period deadline.',
        ])->save();

        $movedRoster->learner->forceFill([
            'enrollment_status' => EnrollmentStatus::TransferredOut,
            'transfer_effective_date' => '2025-11-15',
        ])->save();

        $this->actingAs($context['admin'])
            ->get(route('admin.submission-monitoring', [
                'school_year_id' => $context['schoolYear']->id,
                'grading_period_id' => $context['gradingPeriod']->id,
                'section_id' => $context['section']->id,
            ]))
            ->assertOk()
            ->assertSeeText('Movement Ready Section')
            ->assertSee('>Ready</span>', false)
            ->assertSeeText('1 / 1')
            ->assertDontSeeText('1 / 2')
            ->assertDontSeeText('Ready to lock');
    }

    public function test_lock_action_locks_only_selected_section_period_and_keeps_locked_submissions_non_editable(): void
    {
        $context = $this->createReadyQuarterContext();
        $otherSection = Section::factory()->create([
            'school_year_id' => $context['schoolYear']->id,
            'grade_level_id' => $context['gradeLevel']->id,
            'adviser_id' => $this->createUserWithRole(RoleName::Adviser->value)->id,
            'name' => 'Other Locked Section',
        ]);
        $otherTeacher = $this->createUserWithRole(RoleName::Teacher->value);
        $otherLoad = $this->createTeacherLoad($otherSection, $context['schoolYear'], $otherTeacher, 'Unrelated Approved Subject', 'UAS');
        $otherSubmission = $this->createSubmission($otherLoad, $context['gradingPeriod'], GradeSubmissionStatus::Approved, submittedAt: now()->subDay());

        $this->actingAs($context['admin'])
            ->post(route('admin.submission-monitoring.sections.lock', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertRedirect(route('admin.submission-monitoring', [
                'school_year_id' => $context['schoolYear']->id,
                'grading_period_id' => $context['gradingPeriod']->id,
                'section_id' => $context['section']->id,
            ]));

        $context['submission']->refresh();
        $otherSubmission->refresh();

        $this->assertSame(GradeSubmissionStatus::Locked, $context['submission']->status);
        $this->assertNotNull($context['submission']->locked_at);
        $this->assertSame(GradeSubmissionStatus::Approved, $otherSubmission->status);
        $this->assertDatabaseHas('approval_logs', [
            'grade_submission_id' => $context['submission']->id,
            'acted_by' => $context['admin']->id,
            'action' => ApprovalAction::Locked->value,
        ]);

        Livewire::actingAs($context['teacher'])
            ->test(GradeEntryPage::class, [
                'teacherLoad' => $context['teacherLoad'],
                'gradingPeriod' => $context['gradingPeriod'],
            ])
            ->assertSee('locked')
            ->set("form.grades.{$context['sectionRoster']->id}.grade", '96')
            ->call('saveDraft')
            ->assertHasErrors(['form.record']);
    }

    public function test_reopen_requires_a_non_empty_reason(): void
    {
        $context = $this->lockQuarterContext($this->createReadyQuarterContext());

        $this->actingAs($context['admin'])
            ->from(route('admin.submission-monitoring', [
                'school_year_id' => $context['schoolYear']->id,
                'grading_period_id' => $context['gradingPeriod']->id,
                'section_id' => $context['section']->id,
            ]))
            ->post(route('admin.submission-monitoring.sections.reopen', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
            ]), [
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');
    }

    public function test_section_quarter_actions_use_icon_buttons_and_reopen_modal_markup(): void
    {
        $readyContext = $this->createReadyQuarterContext();
        $lockedContext = $this->lockQuarterContext($this->createReadyQuarterContext([
            'section_name' => 'Locked Reopen Section',
            'subject_name' => 'Locked Subject',
            'subject_code' => 'LRS1',
            'learner_lrn' => '900000000811',
        ]));

        $this->actingAs($readyContext['admin'])
            ->get(route('admin.submission-monitoring', [
                'school_year_id' => $readyContext['schoolYear']->id,
                'grading_period_id' => $readyContext['gradingPeriod']->id,
                'section_id' => $readyContext['section']->id,
            ]))
            ->assertOk()
            ->assertSee('submission-monitoring__table-actions', false)
            ->assertSee('submission-monitoring__table-action-form', false)
            ->assertSee('submission-monitoring__lock-action', false)
            ->assertSee('submission-monitoring__section-status', false)
            ->assertSee('table-action-button table-action-button--secondary', false)
            ->assertSee('table-action-button table-action-button--primary', false)
            ->assertSee('aria-label="Manage learner exceptions"', false)
            ->assertSee('aria-label="Lock quarter records"', false)
            ->assertSeeText('Exceptions')
            ->assertSeeText('Lock')
            ->assertDontSeeText('Manage learner exceptions')
            ->assertDontSeeText('Lock quarter records')
            ->assertDontSeeText('Ready to lock');

        $this->actingAs($lockedContext['admin'])
            ->get(route('admin.submission-monitoring', [
                'school_year_id' => $lockedContext['schoolYear']->id,
                'grading_period_id' => $lockedContext['gradingPeriod']->id,
                'section_id' => $lockedContext['section']->id,
            ]))
            ->assertOk()
            ->assertSee('submission-monitoring__reopen-action', false)
            ->assertSee('table-action-button table-action-button--warning', false)
            ->assertSee('data-modal-open="reopen-quarter-records-'.$lockedContext['section']->id.'"', false)
            ->assertSee('data-modal="reopen-quarter-records-'.$lockedContext['section']->id.'"', false)
            ->assertSee('aria-label="Reopen quarter records"', false)
            ->assertSeeText('Reopen')
            ->assertSeeText('Reopen section quarter')
            ->assertDontSee('data-confirm-message="Reopen this locked section quarter?', false);
    }

    public function test_reopen_validation_marks_the_matching_modal_for_auto_open(): void
    {
        $context = $this->lockQuarterContext($this->createReadyQuarterContext());

        $this->actingAs($context['admin'])
            ->followingRedirects()
            ->from(route('admin.submission-monitoring', [
                'school_year_id' => $context['schoolYear']->id,
                'grading_period_id' => $context['gradingPeriod']->id,
                'section_id' => $context['section']->id,
            ]))
            ->post(route('admin.submission-monitoring.sections.reopen', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
            ]), [
                'reason' => '',
                'reopen_section_id' => $context['section']->id,
            ])
            ->assertOk()
            ->assertSee('data-modal="reopen-quarter-records-'.$context['section']->id.'"', false)
            ->assertSee('data-modal-auto-open="true"', false)
            ->assertSeeText('The reason field is required.');
    }

    public function test_reopen_is_scoped_to_the_selected_section_and_period_and_returns_records_to_review_path(): void
    {
        $target = $this->lockQuarterContext($this->createReadyQuarterContext([
            'section_name' => 'Target Reopen Section',
        ]));

        $otherPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $target['schoolYear']->id,
            'quarter' => GradingQuarter::Second,
            'ends_on' => now()->addWeek()->toDateString(),
        ]);

        $otherSection = Section::factory()->create([
            'school_year_id' => $target['schoolYear']->id,
            'grade_level_id' => $target['gradeLevel']->id,
            'adviser_id' => $this->createUserWithRole(RoleName::Adviser->value)->id,
            'name' => 'Unrelated Locked Section',
        ]);

        $sameSectionOtherPeriodLoad = $this->createTeacherLoad(
            $target['section'],
            $target['schoolYear'],
            $this->createUserWithRole(RoleName::Teacher->value),
            'Other Period Subject',
            'OPS',
        );
        $sameSectionOtherPeriodSubmission = $this->createSubmission(
            $sameSectionOtherPeriodLoad,
            $otherPeriod,
            GradeSubmissionStatus::Locked,
            submittedAt: now()->subDay(),
            lockedAt: now()->subHour(),
        );

        $otherSectionLoad = $this->createTeacherLoad(
            $otherSection,
            $target['schoolYear'],
            $this->createUserWithRole(RoleName::Teacher->value),
            'Other Section Subject',
            'OSS',
        );
        $otherSectionSubmission = $this->createSubmission(
            $otherSectionLoad,
            $target['gradingPeriod'],
            GradeSubmissionStatus::Locked,
            submittedAt: now()->subDay(),
            lockedAt: now()->subHour(),
        );

        $otherSectionRoster = $this->createOfficialRoster($otherSection, $target['schoolYear'], 'Other', '900000009999');
        $otherSectionRecord = $this->createReportCardRecord(
            $otherSectionRoster,
            $otherSection,
            $target['gradingPeriod'],
            $target['adviser'],
            true,
        );
        $sameSectionOtherPeriodRecord = $this->createReportCardRecord(
            $target['sectionRoster'],
            $target['section'],
            $otherPeriod,
            $target['adviser'],
            true,
        );
        $targetSf10Record = $this->createReportCardRecord(
            $target['sectionRoster'],
            $target['section'],
            $otherPeriod,
            $target['adviser'],
            true,
            TemplateDocumentType::Sf10,
        );
        $otherSectionSf10Record = $this->createReportCardRecord(
            $otherSectionRoster,
            $otherSection,
            $otherPeriod,
            $target['adviser'],
            true,
            TemplateDocumentType::Sf10,
        );

        $this->actingAs($target['admin'])
            ->post(route('admin.submission-monitoring.sections.reopen', [
                'section' => $target['section'],
                'grading_period' => $target['gradingPeriod'],
            ]), [
                'reason' => 'General average must be corrected before review resumes.',
            ])
            ->assertRedirect(route('admin.submission-monitoring', [
                'school_year_id' => $target['schoolYear']->id,
                'grading_period_id' => $target['gradingPeriod']->id,
                'section_id' => $target['section']->id,
            ]));

        $target['submission']->refresh();
        $target['reportCardRecord']->refresh();
        $sameSectionOtherPeriodSubmission->refresh();
        $otherSectionSubmission->refresh();
        $otherSectionRecord->refresh();
        $sameSectionOtherPeriodRecord->refresh();
        $targetSf10Record->refresh();
        $otherSectionSf10Record->refresh();

        $this->assertSame(GradeSubmissionStatus::Returned, $target['submission']->status);
        $this->assertNull($target['submission']->approved_at);
        $this->assertNull($target['submission']->locked_at);
        $this->assertNotNull($target['submission']->returned_at);
        $this->assertStringContainsString('Admin reopen reason: General average must be corrected before review resumes.', $target['submission']->adviser_remarks);
        $this->assertFalse($target['reportCardRecord']->is_finalized);
        $this->assertFalse($targetSf10Record->is_finalized);

        $this->assertSame(GradeSubmissionStatus::Locked, $sameSectionOtherPeriodSubmission->status);
        $this->assertSame(GradeSubmissionStatus::Locked, $otherSectionSubmission->status);
        $this->assertTrue($otherSectionRecord->is_finalized);
        $this->assertTrue($sameSectionOtherPeriodRecord->is_finalized);
        $this->assertTrue($otherSectionSf10Record->is_finalized);

        $this->assertDatabaseHas('approval_logs', [
            'grade_submission_id' => $target['submission']->id,
            'acted_by' => $target['admin']->id,
            'action' => ApprovalAction::Reopened->value,
            'remarks' => 'General average must be corrected before review resumes.',
        ]);

        Livewire::actingAs($target['teacher'])
            ->test(GradeEntryPage::class, [
                'teacherLoad' => $target['teacherLoad'],
                'gradingPeriod' => $target['gradingPeriod'],
            ])
            ->assertSee('Admin reopen reason')
            ->set("form.grades.{$target['sectionRoster']->id}.grade", '94')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertSet('workflow.status.value', GradeSubmissionStatus::Returned->value)
            ->call('submitGrades')
            ->assertHasNoErrors()
            ->assertSet('workflow.status.value', GradeSubmissionStatus::Submitted->value);

        $target['submission']->refresh();

        $this->assertSame(GradeSubmissionStatus::Submitted, $target['submission']->status);
        $this->assertNull($target['submission']->approved_at);
        $this->assertFalse($target['reportCardRecord']->fresh()->is_finalized);
    }

    public function test_audit_page_filters_aggregated_events_by_date_user_action_and_module(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value, ['name' => 'Audit Admin']);
        $otherUser = $this->createUserWithRole(RoleName::Teacher->value, ['name' => 'Audit Other']);
        $statusActor = $this->createUserWithRole(RoleName::Adviser->value, ['name' => 'Year End Adviser']);
        $auditContext = $this->createReadyQuarterContext([
            'admin' => $admin,
            'section_name' => 'Audit Section',
        ]);

        $approvalLog = ApprovalLog::factory()->create([
            'grade_submission_id' => $auditContext['submission']->id,
            'acted_by' => $admin->id,
            'action' => ApprovalAction::Locked,
            'remarks' => 'Locked grading remark',
            'created_at' => now()->subHour(),
        ]);

        $template = Template::factory()->create(['name' => 'Audit Template']);
        TemplateAuditLog::factory()->create([
            'template_id' => $template->id,
            'acted_by' => $admin->id,
            'action' => TemplateAuditAction::Activated,
            'remarks' => 'Activated template remark',
            'created_at' => now()->subMinutes(45),
        ]);

        $gradingSheetExport = GradingSheetExport::factory()->create([
            'teacher_load_id' => $auditContext['teacherLoad']->id,
            'grading_period_id' => $auditContext['gradingPeriod']->id,
            'exported_by' => $otherUser->id,
        ]);
        GradingSheetExportAuditLog::factory()->create([
            'grading_sheet_export_id' => $gradingSheetExport->id,
            'acted_by' => $otherUser->id,
            'action' => GradingSheetExportAuditAction::Exported,
            'remarks' => 'Grading sheet export remark',
            'created_at' => now()->subMinutes(30),
        ]);

        ReportCardRecordAuditLog::factory()->create([
            'report_card_record_id' => $auditContext['reportCardRecord']->id,
            'acted_by' => $admin->id,
            'action' => ReportCardRecordAuditAction::Finalized,
            'remarks' => 'SF9 finalization remark',
            'created_at' => now()->subMinutes(15),
        ]);

        $sf10Record = $this->createReportCardRecord(
            $auditContext['sectionRoster'],
            $auditContext['section'],
            $auditContext['gradingPeriod'],
            $auditContext['adviser'],
            true,
            TemplateDocumentType::Sf10,
        );
        ReportCardRecordAuditLog::factory()->create([
            'report_card_record_id' => $sf10Record->id,
            'acted_by' => $admin->id,
            'action' => ReportCardRecordAuditAction::Finalized,
            'remarks' => 'SF10 finalization remark',
            'created_at' => now()->subMinutes(10),
        ]);

        LearnerStatusAuditLog::factory()->create([
            'section_roster_id' => $auditContext['sectionRoster']->id,
            'acted_by' => $statusActor->id,
            'action' => 'year_end_status_updated',
            'remarks' => 'Year-end status changed to promoted.',
            'metadata' => [
                'current_year_end_status' => 'promoted',
            ],
            'created_at' => now()->subMinutes(20),
        ]);

        $importBatch = ImportBatch::factory()->create([
            'section_id' => $auditContext['section']->id,
            'imported_by' => $otherUser->id,
            'source_file_name' => 'audit-import.xlsx',
            'created_at' => now()->subHours(2),
            'confirmed_at' => now()->subHours(1),
            'confirmed_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.submission-monitoring.audit'))
            ->assertOk()
            ->assertSeeText('Locked grading remark')
            ->assertSeeText('Activated template remark')
            ->assertSeeText('Grading sheet export remark')
            ->assertSeeText('SF9 finalization remark')
            ->assertSeeText('SF10 finalization remark')
            ->assertSeeText('Year-end status changed to promoted.')
            ->assertSeeText('audit-import.xlsx');

        $this->actingAs($admin)
            ->get(route('admin.submission-monitoring.audit', [
                'from_date' => now()->toDateString(),
                'to_date' => now()->toDateString(),
                'user_id' => $admin->id,
                'action' => ApprovalAction::Locked->value,
                'module' => 'grading-workflow',
            ]))
            ->assertOk()
            ->assertSeeText('Locked grading remark')
            ->assertDontSeeText('Activated template remark')
            ->assertDontSeeText('Grading sheet export remark')
            ->assertDontSeeText('SF9 finalization remark')
            ->assertDontSeeText('SF10 finalization remark')
            ->assertDontSeeText('Year-end status changed to promoted.')
            ->assertDontSeeText($importBatch->source_file_name);

        $this->actingAs($admin)
            ->get(route('admin.submission-monitoring.audit', [
                'user_id' => $admin->id,
                'action' => ReportCardRecordAuditAction::Finalized->value,
                'module' => 'sf10-records',
            ]))
            ->assertOk()
            ->assertSeeText('SF10 finalization remark')
            ->assertDontSeeText('SF9 finalization remark')
            ->assertDontSeeText('Year-end status changed to promoted.')
            ->assertDontSeeText($importBatch->source_file_name);

        $this->actingAs($admin)
            ->get(route('admin.submission-monitoring.audit', [
                'user_id' => $admin->id,
                'action' => 'confirmed',
                'module' => 'sf1-imports',
            ]))
            ->assertOk()
            ->assertSeeText($importBatch->source_file_name)
            ->assertDontSeeText('Locked grading remark')
            ->assertDontSeeText('SF10 finalization remark');

        $approvalLog->refresh();
        $this->assertSame(ApprovalAction::Locked, $approvalLog->action);
    }

    private function createReadyQuarterContext(array $overrides = []): array
    {
        $admin = $overrides['admin'] ?? $this->createUserWithRole(RoleName::Admin->value);
        $adviser = $overrides['adviser'] ?? $this->createUserWithRole(RoleName::Adviser->value);
        $teacher = $overrides['teacher'] ?? $this->createUserWithRole(RoleName::Teacher->value);
        $gradeLevel = $overrides['gradeLevel'] ?? GradeLevel::factory()->create(['name' => 'Grade 7']);
        $schoolYear = $overrides['schoolYear'] ?? SchoolYear::factory()->create(['is_active' => true]);
        $gradingPeriod = $overrides['gradingPeriod'] ?? GradingPeriod::factory()->create([
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::First,
            'ends_on' => now()->addWeek()->toDateString(),
            'is_open' => true,
        ]);
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
            'name' => $overrides['section_name'] ?? 'Section Ready',
        ]);
        $teacherLoad = $this->createTeacherLoad(
            $section,
            $schoolYear,
            $teacher,
            $overrides['subject_name'] ?? 'Ready Subject',
            $overrides['subject_code'] ?? 'RS1',
        );
        $submission = $this->createSubmission(
            $teacherLoad,
            $gradingPeriod,
            GradeSubmissionStatus::Approved,
            submittedAt: now()->subDay(),
            approvedAt: now()->subHours(2),
        );
        $sectionRoster = $this->createOfficialRoster(
            $section,
            $schoolYear,
            $overrides['learner_first_name'] ?? 'Ready',
            $overrides['learner_lrn'] ?? '900000000111',
        );

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $sectionRoster->id,
            'grade' => 91,
            'remarks' => 'Passed',
        ]);

        $reportCardRecord = $this->createReportCardRecord(
            $sectionRoster,
            $section,
            $gradingPeriod,
            $adviser,
            true,
        );

        return compact(
            'admin',
            'adviser',
            'teacher',
            'gradeLevel',
            'schoolYear',
            'gradingPeriod',
            'section',
            'teacherLoad',
            'submission',
            'sectionRoster',
            'reportCardRecord',
        );
    }

    private function lockQuarterContext(array $context): array
    {
        $this->actingAs($context['admin'])
            ->post(route('admin.submission-monitoring.sections.lock', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertRedirect();

        $context['submission']->refresh();

        return $context;
    }

    private function createTeacherLoad(
        Section $section,
        SchoolYear $schoolYear,
        User $teacher,
        string $subjectName,
        string $subjectCode,
    ): TeacherLoad {
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
        ?string $remarks = null,
        mixed $submittedAt = null,
        mixed $returnedAt = null,
        mixed $approvedAt = null,
        mixed $lockedAt = null,
    ): GradeSubmission {
        return GradeSubmission::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
            'grading_period_id' => $gradingPeriod->id,
            'status' => $status,
            'submitted_by' => $teacherLoad->teacher_id,
            'adviser_remarks' => $remarks,
            'submitted_at' => $submittedAt ?? ($status === GradeSubmissionStatus::Draft ? null : now()->subHour()),
            'returned_at' => $returnedAt ?? ($status === GradeSubmissionStatus::Returned ? now()->subMinutes(20) : null),
            'approved_at' => $approvedAt ?? ($status === GradeSubmissionStatus::Approved ? now()->subMinutes(10) : null),
            'locked_at' => $lockedAt ?? ($status === GradeSubmissionStatus::Locked ? now()->subMinutes(5) : null),
        ]);
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
            'is_official' => true,
        ]);
    }

    private function createReportCardRecord(
        SectionRoster $sectionRoster,
        Section $section,
        GradingPeriod $gradingPeriod,
        User $adviser,
        bool $isFinalized,
        TemplateDocumentType $documentType = TemplateDocumentType::Sf9,
    ): ReportCardRecord {
        return ReportCardRecord::factory()->create([
            'section_roster_id' => $sectionRoster->id,
            'section_id' => $section->id,
            'school_year_id' => $section->school_year_id,
            'learner_id' => $sectionRoster->learner_id,
            'grading_period_id' => $gradingPeriod->id,
            'document_type' => $documentType,
            'generated_by' => $adviser->id,
            'is_finalized' => $isFinalized,
            'finalized_at' => $isFinalized ? now()->subHour() : null,
            'finalized_by' => $isFinalized ? $adviser->id : null,
        ]);
    }

    private function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
