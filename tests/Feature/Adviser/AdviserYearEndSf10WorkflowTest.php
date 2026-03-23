<?php

namespace Tests\Feature\Adviser;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Enums\LearnerYearEndStatus;
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
use App\Models\SystemSetting;
use App\Models\TeacherLoad;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AdviserYearEndSf10WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        SystemSetting::factory()->create([
            'key' => 'school.profile',
            'value' => ['name' => 'Seamless Grading Demo School'],
            'is_public' => true,
        ]);
    }

    public function test_year_end_and_sf10_routes_require_owned_advisory_sections_and_official_rosters(): void
    {
        $context = $this->createContext();
        $roster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000001001');
        $this->seedApprovedYearData(
            $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH'),
            $context['gradingPeriods'],
            $roster,
            [90, 91, 92, 93],
        );
        $roster->update(['year_end_status' => LearnerYearEndStatus::Promoted]);
        $this->createActiveSf10Template($context['section']);

        $otherAdviser = $this->createUserWithRole(RoleName::Adviser->value);
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $unofficialRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Hidden', '900000001002', [
            'is_official' => false,
        ]);

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.year-end.index', ['section' => $context['section']]))
            ->assertOk()
            ->assertSeeText('Learner Status Management');

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.sf10.show', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertOk()
            ->assertSeeText('Learner year-end context');

        $this->actingAs($otherAdviser)
            ->get(route('adviser.sections.year-end.index', ['section' => $context['section']]))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($otherAdviser)
            ->get(route('adviser.sections.sf10.show', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($teacher)
            ->post(route('adviser.sections.sf10.export', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertForbidden();

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.sf10.show', [
                'section' => $context['section'],
                'section_roster' => $unofficialRoster,
            ]))
            ->assertNotFound();
    }

    public function test_year_end_status_page_uses_official_rosters_and_safe_status_updates_are_audited(): void
    {
        $context = $this->createContext();
        $readyRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000001011');
        $blockedRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Marco', '900000001012');
        $this->createRoster($context['section'], $context['schoolYear'], 'Hidden', '900000001013', [
            'is_official' => false,
        ]);

        $mathLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $scienceLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Science', 'SCI');

        $this->seedApprovedYearData($mathLoad, $context['gradingPeriods'], $readyRoster, [90, 91, 92, 93]);
        $this->seedApprovedYearData($scienceLoad, $context['gradingPeriods'], $readyRoster, [89, 90, 91, 92]);
        $this->seedApprovedYearData($mathLoad, $context['gradingPeriods']->take(3), $blockedRoster, [85, 86, 87]);

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.year-end.index', ['section' => $context['section']]))
            ->assertOk()
            ->assertSeeText('Alicia')
            ->assertSeeText('Marco')
            ->assertDontSeeText('Hidden')
            ->assertSeeText('Full year: Ready')
            ->assertSeeText('Full year: Blocked');

        $this->actingAs($context['adviser'])
            ->put(route('adviser.sections.year-end.update', [
                'section' => $context['section'],
                'section_roster' => $readyRoster,
            ]), [
                'status' => LearnerYearEndStatus::Promoted->value,
                'reason' => '',
            ])
            ->assertRedirect(route('adviser.sections.year-end.index', ['section' => $context['section']]));

        $readyRoster->refresh();

        $this->assertSame(LearnerYearEndStatus::Promoted, $readyRoster->year_end_status);
        $this->assertNotNull($readyRoster->year_end_status_set_at);
        $this->assertSame($context['adviser']->id, $readyRoster->year_end_status_set_by);
        $this->assertDatabaseHas('learner_status_audit_logs', [
            'section_roster_id' => $readyRoster->id,
            'acted_by' => $context['adviser']->id,
            'action' => 'year_end_status_updated',
        ]);
    }

    public function test_status_updates_block_incomplete_promotion_flow_and_require_reason_for_exception_statuses(): void
    {
        $context = $this->createContext();
        $blockedRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Marco', '900000001021');
        $transferRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Lina', '900000001022');
        $transferRoster->learner->update([
            'enrollment_status' => EnrollmentStatus::TransferredOut,
        ]);

        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $this->seedApprovedYearData($load, $context['gradingPeriods']->take(2), $blockedRoster, [88, 89]);
        $this->seedApprovedYearData($load, $context['gradingPeriods'], $transferRoster, [90, 91, 92, 93]);

        $this->actingAs($context['adviser'])
            ->from(route('adviser.sections.year-end.index', ['section' => $context['section']]))
            ->put(route('adviser.sections.year-end.update', [
                'section' => $context['section'],
                'section_roster' => $blockedRoster,
            ]), [
                'status' => LearnerYearEndStatus::Promoted->value,
                'reason' => '',
            ])
            ->assertRedirect(route('adviser.sections.year-end.index', ['section' => $context['section']]))
            ->assertSessionHasErrors('status');

        $this->actingAs($context['adviser'])
            ->from(route('adviser.sections.year-end.index', ['section' => $context['section']]))
            ->put(route('adviser.sections.year-end.update', [
                'section' => $context['section'],
                'section_roster' => $blockedRoster,
            ]), [
                'status' => LearnerYearEndStatus::TransferredOut->value,
                'reason' => '',
            ])
            ->assertRedirect(route('adviser.sections.year-end.index', ['section' => $context['section']]))
            ->assertSessionHasErrors('reason');

        $this->actingAs($context['adviser'])
            ->put(route('adviser.sections.year-end.update', [
                'section' => $context['section'],
                'section_roster' => $blockedRoster,
            ]), [
                'status' => LearnerYearEndStatus::TransferredOut->value,
                'reason' => 'Transferred to another district before year-end completion.',
            ])
            ->assertRedirect(route('adviser.sections.year-end.index', ['section' => $context['section']]));

        $blockedRoster->refresh();
        $this->assertSame(LearnerYearEndStatus::TransferredOut, $blockedRoster->year_end_status);
        $this->assertSame('Transferred to another district before year-end completion.', $blockedRoster->year_end_status_reason);

        $this->actingAs($context['adviser'])
            ->from(route('adviser.sections.year-end.index', ['section' => $context['section']]))
            ->put(route('adviser.sections.year-end.update', [
                'section' => $context['section'],
                'section_roster' => $transferRoster,
            ]), [
                'status' => LearnerYearEndStatus::Promoted->value,
                'reason' => '',
            ])
            ->assertRedirect(route('adviser.sections.year-end.index', ['section' => $context['section']]))
            ->assertSessionHasErrors('status');
    }

    public function test_year_end_status_updates_invalidate_existing_finalized_sf10_records_until_reexport_and_refinalization(): void
    {
        $context = $this->createContext();
        $roster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000001026');
        $mathLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $scienceLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Science', 'SCI');
        $registrar = $this->createUserWithRole(RoleName::Registrar->value, ['name' => 'Registrar Visibility']);

        $this->seedApprovedYearData($mathLoad, $context['gradingPeriods'], $roster, [90, 91, 92, 93]);
        $this->seedApprovedYearData($scienceLoad, $context['gradingPeriods'], $roster, [88, 89, 90, 91]);

        $roster->update([
            'year_end_status' => LearnerYearEndStatus::Promoted,
            'year_end_status_set_at' => now(),
            'year_end_status_set_by' => $context['adviser']->id,
        ]);

        $this->createActiveSf10Template($context['section']);

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.export', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertOk();

        $draftRecord = ReportCardRecord::query()
            ->where('document_type', TemplateDocumentType::Sf10)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.finalize', [
                'section' => $context['section'],
                'section_roster' => $roster,
                'report_card_record' => $draftRecord,
            ]))
            ->assertRedirect();

        $draftRecord->refresh();
        $this->assertTrue($draftRecord->is_finalized);

        $this->actingAs($registrar)
            ->get(route('registrar.records.index'))
            ->assertOk()
            ->assertSeeText('Alicia');

        $this->actingAs($context['adviser'])
            ->put(route('adviser.sections.year-end.update', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]), [
                'status' => LearnerYearEndStatus::Retained->value,
                'reason' => '',
            ])
            ->assertRedirect(route('adviser.sections.year-end.index', ['section' => $context['section']]));

        $draftRecord->refresh();

        $this->assertFalse($draftRecord->is_finalized);
        $this->actingAs($registrar)
            ->get(route('registrar.records.index'))
            ->assertOk()
            ->assertDontSeeText('Alicia');

        $statusAudit = LearnerStatusAuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame('retained', $statusAudit->metadata['current_year_end_status']);
        $this->assertSame(1, $statusAudit->metadata['invalidated_sf10_record_count']);
    }

    public function test_sf10_preview_and_export_are_blocked_until_status_template_and_approved_full_year_data_are_complete(): void
    {
        $context = $this->createContext();
        $roster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000001031');
        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $this->seedYearDataWithStatuses($load, $context['gradingPeriods'], $roster, [
            GradeSubmissionStatus::Approved,
            GradeSubmissionStatus::Approved,
            GradeSubmissionStatus::Approved,
            GradeSubmissionStatus::Submitted,
        ], [90, 91, 92, 93]);

        $previewRoute = route('adviser.sections.sf10.show', [
            'section' => $context['section'],
            'section_roster' => $roster,
        ]);

        $this->actingAs($context['adviser'])
            ->get($previewRoute)
            ->assertOk()
            ->assertSeeText('Set a learner year-end status before preparing or exporting SF10')
            ->assertSeeText('No active SF10 template exists')
            ->assertSeeText('Mathematics is submitted for Q4');

        $this->actingAs($context['adviser'])
            ->from($previewRoute)
            ->post(route('adviser.sections.sf10.export', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertRedirect($previewRoute)
            ->assertSessionHasErrors('record');
    }

    public function test_sf10_export_uses_only_pre_exception_approved_year_data_for_dropped_learners(): void
    {
        $context = $this->createContext();
        $this->assignGradingPeriodDates($context['gradingPeriods']);

        $roster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000001036');
        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');

        $this->seedApprovedYearData($load, $context['gradingPeriods']->take(2), $roster, [90, 91]);

        $effectiveDate = $context['gradingPeriods']->values()->get(2)->starts_on->toDateString();

        $roster->forceFill([
            'enrollment_status' => EnrollmentStatus::Dropped,
            'withdrawn_on' => $effectiveDate,
            'movement_reason' => 'Stopped attending after the second quarter.',
            'year_end_status' => LearnerYearEndStatus::Dropped,
            'year_end_status_reason' => 'Stopped attending after the second quarter.',
            'year_end_status_set_at' => now(),
            'year_end_status_set_by' => $context['adviser']->id,
        ])->save();

        $roster->learner->forceFill([
            'enrollment_status' => EnrollmentStatus::Dropped,
        ])->save();

        $this->createActiveSf10Template($context['section']);

        $previewRoute = route('adviser.sections.sf10.show', [
            'section' => $context['section'],
            'section_roster' => $roster,
        ]);

        $this->actingAs($context['adviser'])
            ->get($previewRoute)
            ->assertOk()
            ->assertSeeText('Dropped')
            ->assertSeeText('Full year ready')
            ->assertDontSeeText('No approved final year-end subject data exists yet for this learner.');

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.export', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertOk();

        $this->assertDatabaseHas('report_card_records', [
            'section_roster_id' => $roster->id,
            'document_type' => TemplateDocumentType::Sf10->value,
            'record_version' => 1,
        ]);
    }

    public function test_sf10_export_creates_versioned_history_from_active_validated_template_and_new_exports_preserve_old_files(): void
    {
        $context = $this->createContext();
        $roster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000001041');
        $mathLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $scienceLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Science', 'SCI');

        $mathGrades = $this->seedApprovedYearData($mathLoad, $context['gradingPeriods'], $roster, [90, 91, 92, 93]);
        $this->seedApprovedYearData($scienceLoad, $context['gradingPeriods'], $roster, [88, 89, 90, 91]);

        $roster->update([
            'year_end_status' => LearnerYearEndStatus::Promoted,
            'year_end_status_set_at' => now(),
            'year_end_status_set_by' => $context['adviser']->id,
        ]);

        $this->createActiveSf10Template($context['section'], [
            'school_name' => 'B2',
            'learner_name' => 'B4',
            'learner_lrn' => 'F4',
            'grade_level_name' => 'B6',
            'school_year_name' => 'F6',
            'section_name' => 'B8',
            'adviser_name' => 'F8',
            'learner_status' => 'B10',
            'general_average' => 'F10',
            'subject_name_column' => 'A14',
            'final_rating_column' => 'F14',
            'action_taken_column' => 'H14',
        ], version: 2);

        $response = $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.export', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]));

        $firstRecord = ReportCardRecord::query()->latest('id')->firstOrFail();

        $response->assertOk();
        $this->assertDatabaseHas('report_card_records', [
            'id' => $firstRecord->id,
            'section_roster_id' => $roster->id,
            'section_id' => $context['section']->id,
            'school_year_id' => $context['schoolYear']->id,
            'learner_id' => $roster->learner_id,
            'grading_period_id' => $context['gradingPeriods']->last()->id,
            'document_type' => TemplateDocumentType::Sf10->value,
            'template_version' => 2,
            'record_version' => 1,
            'generated_by' => $context['adviser']->id,
        ]);
        $this->assertDatabaseHas('report_card_record_audit_logs', [
            'report_card_record_id' => $firstRecord->id,
            'acted_by' => $context['adviser']->id,
            'action' => 'exported',
        ]);

        $firstWorkbook = IOFactory::load(Storage::disk('local')->path($firstRecord->file_path));
        $this->assertSame('Seamless Grading Demo School', $firstWorkbook->getActiveSheet()->getCell('B2')->getValue());
        $this->assertStringContainsString('Alicia', (string) $firstWorkbook->getActiveSheet()->getCell('B4')->getValue());
        $this->assertSame('900000001041', preg_replace('/\\.0$/', '', (string) $firstWorkbook->getActiveSheet()->getCell('F4')->getValue()));
        $this->assertSame($context['gradeLevel']->name, $firstWorkbook->getActiveSheet()->getCell('B6')->getValue());
        $this->assertSame($context['schoolYear']->name, $firstWorkbook->getActiveSheet()->getCell('F6')->getValue());
        $this->assertSame($context['section']->name, $firstWorkbook->getActiveSheet()->getCell('B8')->getValue());
        $this->assertSame($context['adviser']->name, $firstWorkbook->getActiveSheet()->getCell('F8')->getValue());
        $this->assertSame('Promoted', $firstWorkbook->getActiveSheet()->getCell('B10')->getValue());
        $this->assertSame(90.5, (float) $firstWorkbook->getActiveSheet()->getCell('F10')->getValue());
        $this->assertSame('Mathematics', $firstWorkbook->getActiveSheet()->getCell('A14')->getValue());
        $this->assertSame(91.5, (float) $firstWorkbook->getActiveSheet()->getCell('F14')->getValue());
        $this->assertSame('Promoted', $firstWorkbook->getActiveSheet()->getCell('H14')->getValue());

        $mathGrades->last()->update(['grade' => 97]);

        $secondResponse = $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.export', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]));

        $secondRecord = ReportCardRecord::query()
            ->where('document_type', TemplateDocumentType::Sf10)
            ->latest('id')
            ->firstOrFail();

        $secondResponse->assertOk();
        $this->assertSame(1, $firstRecord->record_version);
        $this->assertSame(2, $secondRecord->record_version);
        $this->assertNotSame($firstRecord->file_path, $secondRecord->file_path);
        Storage::disk('local')->assertExists($firstRecord->file_path);
        Storage::disk('local')->assertExists($secondRecord->file_path);

        $secondWorkbook = IOFactory::load(Storage::disk('local')->path($secondRecord->file_path));
        $this->assertSame(92.5, (float) $secondWorkbook->getActiveSheet()->getCell('F14')->getValue());

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.sf10.show', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertOk()
            ->assertSeeText('Version 2')
            ->assertSeeText('Version 1');
    }

    public function test_sf10_finalization_is_blocked_for_stale_drafts_and_requires_the_latest_matching_review_record(): void
    {
        $context = $this->createContext();
        $roster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000001051');
        $mathLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $scienceLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Science', 'SCI');

        $mathGrades = $this->seedApprovedYearData($mathLoad, $context['gradingPeriods'], $roster, [90, 91, 92, 93]);
        $this->seedApprovedYearData($scienceLoad, $context['gradingPeriods'], $roster, [88, 89, 90, 91]);

        $roster->update([
            'year_end_status' => LearnerYearEndStatus::Promoted,
            'year_end_status_set_at' => now(),
            'year_end_status_set_by' => $context['adviser']->id,
        ]);

        $this->createActiveSf10Template($context['section']);

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.export', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertOk();

        $draftRecord = ReportCardRecord::query()
            ->where('document_type', TemplateDocumentType::Sf10)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.sf10.show', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertOk()
            ->assertSeeText('Draft review pending')
            ->assertSeeText('Finalize SF10 for registrar handoff');

        $mathGrades->last()->update(['grade' => 97]);

        $this->actingAs($context['adviser'])
            ->from(route('adviser.sections.sf10.show', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->post(route('adviser.sections.sf10.finalize', [
                'section' => $context['section'],
                'section_roster' => $roster,
                'report_card_record' => $draftRecord,
            ]))
            ->assertRedirect(route('adviser.sections.sf10.show', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertSessionHasErrors('record');

        $draftRecord->refresh();
        $this->assertFalse($draftRecord->is_finalized);

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.sf10.show', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertOk()
            ->assertSeeText('The latest SF10 draft no longer matches the current approved year-end data or active template');
    }

    public function test_sf10_finalization_is_explicit_audited_and_enables_registrar_repository_visibility(): void
    {
        $context = $this->createContext();
        $roster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000001061');
        $mathLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $scienceLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Science', 'SCI');

        $this->seedApprovedYearData($mathLoad, $context['gradingPeriods'], $roster, [90, 91, 92, 93]);
        $this->seedApprovedYearData($scienceLoad, $context['gradingPeriods'], $roster, [88, 89, 90, 91]);

        $roster->update([
            'year_end_status' => LearnerYearEndStatus::Promoted,
            'year_end_status_set_at' => now(),
            'year_end_status_set_by' => $context['adviser']->id,
        ]);

        $this->createActiveSf10Template($context['section'], version: 3);

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.export', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertOk();

        $draftRecord = ReportCardRecord::query()
            ->where('document_type', TemplateDocumentType::Sf10)
            ->latest('id')
            ->firstOrFail();

        $registrar = $this->createUserWithRole(RoleName::Registrar->value, ['name' => 'Registrar Review']);
        $otherAdviser = $this->createUserWithRole(RoleName::Adviser->value, ['name' => 'Other Adviser']);

        $this->actingAs($registrar)
            ->get(route('registrar.records.index'))
            ->assertOk()
            ->assertDontSeeText('Alicia');

        $this->actingAs($otherAdviser)
            ->post(route('adviser.sections.sf10.finalize', [
                'section' => $context['section'],
                'section_roster' => $roster,
                'report_card_record' => $draftRecord,
            ]))
            ->assertForbidden();

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.finalize', [
                'section' => $context['section'],
                'section_roster' => $roster,
                'report_card_record' => $draftRecord,
            ]))
            ->assertRedirect(route('adviser.sections.sf10.show', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]));

        $draftRecord->refresh();

        $this->assertTrue($draftRecord->is_finalized);
        $this->assertNotNull($draftRecord->finalized_at);
        $this->assertSame($context['adviser']->id, $draftRecord->finalized_by);
        $this->assertDatabaseHas('report_card_record_audit_logs', [
            'report_card_record_id' => $draftRecord->id,
            'acted_by' => $context['adviser']->id,
            'action' => 'finalized',
        ]);

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.sf10.show', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertOk()
            ->assertSeeText('Finalized')
            ->assertSeeText('eligible for the registrar final-records repository');

        $this->actingAs($registrar)
            ->get(route('registrar.records.index'))
            ->assertOk()
            ->assertSeeText('Alicia')
            ->assertSeeText('SF10')
            ->assertSeeText('Version 1');

        $this->actingAs($registrar)
            ->get(route('registrar.records.show', ['report_card_record' => $draftRecord]))
            ->assertOk()
            ->assertSeeText('Finalized official record');
    }

    public function test_newer_finalized_sf10_versions_supersede_older_finalized_versions(): void
    {
        $context = $this->createContext();
        $roster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000001071');
        $mathLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $scienceLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Science', 'SCI');
        $registrar = $this->createUserWithRole(RoleName::Registrar->value);

        $mathGrades = $this->seedApprovedYearData($mathLoad, $context['gradingPeriods'], $roster, [90, 91, 92, 93]);
        $this->seedApprovedYearData($scienceLoad, $context['gradingPeriods'], $roster, [88, 89, 90, 91]);

        $roster->update([
            'year_end_status' => LearnerYearEndStatus::Promoted,
            'year_end_status_set_at' => now(),
            'year_end_status_set_by' => $context['adviser']->id,
        ]);

        $this->createActiveSf10Template($context['section'], version: 4);

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.export', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertOk();

        $firstRecord = ReportCardRecord::query()
            ->where('document_type', TemplateDocumentType::Sf10)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.finalize', [
                'section' => $context['section'],
                'section_roster' => $roster,
                'report_card_record' => $firstRecord,
            ]))
            ->assertRedirect();

        $mathGrades->last()->update(['grade' => 97]);

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.export', [
                'section' => $context['section'],
                'section_roster' => $roster,
            ]))
            ->assertOk();

        $secondRecord = ReportCardRecord::query()
            ->where('document_type', TemplateDocumentType::Sf10)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf10.finalize', [
                'section' => $context['section'],
                'section_roster' => $roster,
                'report_card_record' => $secondRecord,
            ]))
            ->assertRedirect();

        $firstRecord->refresh();
        $secondRecord->refresh();

        $this->assertFalse($firstRecord->is_finalized);
        $this->assertTrue($secondRecord->is_finalized);
        $this->assertSame(
            1,
            ReportCardRecord::query()
                ->where('section_roster_id', $roster->id)
                ->where('document_type', TemplateDocumentType::Sf10)
                ->where('is_finalized', true)
                ->count(),
        );

        $this->actingAs($registrar)
            ->get(route('registrar.records.learners.show', ['learner' => $roster->learner]))
            ->assertOk()
            ->assertSeeText('Version 2')
            ->assertDontSeeText('Version 1');
    }

    private function createContext(): array
    {
        $adviser = $this->createUserWithRole(RoleName::Adviser->value, ['name' => 'Adviser Year End']);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 6']);
        $schoolYear = SchoolYear::factory()->create(['name' => '2044-2045']);
        $section = Section::factory()->create([
            'name' => 'Section Molave',
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);

        $gradingPeriods = collect([
            GradingQuarter::First,
            GradingQuarter::Second,
            GradingQuarter::Third,
            GradingQuarter::Fourth,
        ])->map(fn (GradingQuarter $quarter) => GradingPeriod::factory()->create([
            'school_year_id' => $schoolYear->id,
            'quarter' => $quarter,
            'is_open' => $quarter === GradingQuarter::Fourth,
        ]));

        return compact('adviser', 'gradeLevel', 'schoolYear', 'section', 'gradingPeriods');
    }

    /**
     * @param  Collection<int, GradingPeriod>  $gradingPeriods
     */
    private function assignGradingPeriodDates(Collection $gradingPeriods): void
    {
        $dates = [
            ['starts_on' => '2044-06-10', 'ends_on' => '2044-08-15'],
            ['starts_on' => '2044-09-01', 'ends_on' => '2044-11-15'],
            ['starts_on' => '2045-01-10', 'ends_on' => '2045-02-28'],
            ['starts_on' => '2045-03-10', 'ends_on' => '2045-05-15'],
        ];

        $gradingPeriods->values()->each(function (GradingPeriod $gradingPeriod, int $index) use ($dates): void {
            $gradingPeriod->update($dates[$index]);
        });
    }

    private function createTeacherLoad(
        Section $section,
        SchoolYear $schoolYear,
        string $subjectName,
        string $subjectCode,
    ): TeacherLoad {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value, ['name' => $subjectName.' Teacher']);
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

    private function createRoster(
        Section $section,
        SchoolYear $schoolYear,
        string $firstName,
        string $lrn,
        array $attributes = [],
    ): SectionRoster {
        $learner = Learner::factory()->create([
            'first_name' => $firstName,
            'last_name' => 'Student',
            'lrn' => $lrn,
            'sex' => 'female',
            'enrollment_status' => EnrollmentStatus::Active,
        ]);

        return SectionRoster::factory()->create(array_merge([
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'learner_id' => $learner->id,
            'import_batch_id' => null,
            'enrollment_status' => EnrollmentStatus::Active,
            'is_official' => true,
        ], $attributes));
    }

    /**
     * @param  Collection<int, GradingPeriod>  $gradingPeriods
     * @param  array<int, int|float>  $grades
     * @return Collection<int, QuarterlyGrade>
     */
    private function seedApprovedYearData(
        TeacherLoad $teacherLoad,
        Collection $gradingPeriods,
        SectionRoster $sectionRoster,
        array $grades,
    ): Collection {
        return $this->seedYearDataWithStatuses(
            $teacherLoad,
            $gradingPeriods,
            $sectionRoster,
            array_fill(0, $gradingPeriods->count(), GradeSubmissionStatus::Approved),
            $grades,
        );
    }

    /**
     * @param  Collection<int, GradingPeriod>  $gradingPeriods
     * @param  array<int, GradeSubmissionStatus>  $statuses
     * @param  array<int, int|float>  $grades
     * @return Collection<int, QuarterlyGrade>
     */
    private function seedYearDataWithStatuses(
        TeacherLoad $teacherLoad,
        Collection $gradingPeriods,
        SectionRoster $sectionRoster,
        array $statuses,
        array $grades,
    ): Collection {
        return $gradingPeriods->values()->map(function (GradingPeriod $gradingPeriod, int $index) use ($teacherLoad, $sectionRoster, $statuses, $grades): QuarterlyGrade {
            $status = $statuses[$index];
            $submission = GradeSubmission::query()->firstOrNew([
                'teacher_load_id' => $teacherLoad->id,
                'grading_period_id' => $gradingPeriod->id,
            ]);

            $submission->forceFill([
                'status' => $status,
                'submitted_by' => $teacherLoad->teacher_id,
                'submitted_at' => $status === GradeSubmissionStatus::Draft ? null : now()->subDay(),
                'returned_at' => $status === GradeSubmissionStatus::Returned ? now()->subHours(4) : null,
                'approved_at' => $status === GradeSubmissionStatus::Approved ? now()->subHours(2) : null,
                'locked_at' => $status === GradeSubmissionStatus::Locked ? now()->subHour() : null,
            ])->save();

            $gradeValue = $grades[$index] ?? 90;

            return QuarterlyGrade::query()->updateOrCreate(
                [
                    'grade_submission_id' => $submission->id,
                    'section_roster_id' => $sectionRoster->id,
                ],
                [
                    'grade' => $gradeValue,
                    'remarks' => $gradeValue >= 75 ? 'Passed' : 'Failed',
                ],
            );
        });
    }

    private function createActiveSf10Template(
        Section $section,
        array $mappingOverrides = [],
        int $version = 1,
        string $scope = 'grade-level',
    ): Template {
        $scopeGradeLevelId = $scope === 'grade-level' ? $section->grade_level_id : null;
        $scopeKey = $scopeGradeLevelId === null ? 'global' : 'grade-level:'.$scopeGradeLevelId;
        $activeScopeKey = TemplateDocumentType::Sf10->value.':'.$scopeKey;
        $filePath = 'templates/sf10/'.($scope === 'grade-level' ? 'grade-level' : 'global').'-'.$version.'-'.uniqid().'.xlsx';

        $this->storeTemplateWorkbook($filePath);

        $template = Template::factory()->create([
            'code' => $scope === 'grade-level' ? 'sf10-grade-level' : 'sf10-global',
            'name' => $scope === 'grade-level' ? 'Grade-level SF10' : 'Global SF10',
            'document_type' => TemplateDocumentType::Sf10,
            'grade_level_id' => $scopeGradeLevelId,
            'scope_key' => $scopeKey,
            'version' => $version,
            'file_path' => $filePath,
            'file_disk' => 'local',
            'is_active' => true,
            'active_scope_key' => $activeScopeKey,
            'activated_at' => now(),
        ]);

        $defaultMappings = [
            'school_name' => 'A2',
            'learner_name' => 'A4',
            'learner_lrn' => 'D4',
            'grade_level_name' => 'A6',
            'school_year_name' => 'D6',
            'section_name' => 'A8',
            'adviser_name' => 'D8',
            'learner_status' => 'A10',
            'general_average' => 'D10',
            'subject_name_column' => 'A14',
            'final_rating_column' => 'F14',
            'action_taken_column' => 'H14',
        ];

        foreach (array_merge($defaultMappings, $mappingOverrides) as $fieldKey => $targetCell) {
            $template->fieldMaps()->updateOrCreate(
                ['field_key' => $fieldKey],
                [
                    'target_cell' => $targetCell,
                    'default_value' => null,
                    'is_required' => true,
                ],
            );
        }

        return $template->fresh('fieldMaps');
    }

    private function storeTemplateWorkbook(string $filePath): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('SF10');

        $tempPath = tempnam(sys_get_temp_dir(), 'sf10-template');
        $xlsxPath = $tempPath.'.xlsx';

        rename($tempPath, $xlsxPath);

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);

        Storage::disk('local')->put($filePath, file_get_contents($xlsxPath));

        @unlink($xlsxPath);
    }

    private function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
