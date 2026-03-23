<?php

namespace Tests\Feature\Adviser;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Enums\RoleName;
use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\Learner;
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
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AdviserSf9WorkflowTest extends TestCase
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

    public function test_adviser_sf9_routes_require_owned_sections_and_matching_section_period_roster_scope(): void
    {
        $context = $this->createContext();
        $this->createActiveTemplate($context['section']);

        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submission = $this->createSubmission($load, $context['gradingPeriod'], GradeSubmissionStatus::Approved);
        $officialRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000000101');
        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $officialRoster->id,
            'grade' => 91,
            'remarks' => 'Passed',
        ]);

        $otherAdviser = $this->createUserWithRole(RoleName::Adviser->value);
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $otherSchoolYear = SchoolYear::factory()->create();
        $otherPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $otherSchoolYear->id,
            'quarter' => GradingQuarter::Second,
        ]);
        $unofficialRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Hidden', '900000000102', [
            'is_official' => false,
        ]);

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.sf9.show', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'section_roster' => $officialRoster,
            ]))
            ->assertOk()
            ->assertSeeText('Learner record');

        $this->actingAs($otherAdviser)
            ->get(route('adviser.sections.sf9.show', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'section_roster' => $officialRoster,
            ]))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($teacher)
            ->post(route('adviser.sections.sf9.export', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'section_roster' => $officialRoster,
            ]))
            ->assertForbidden();

        $record = $this->exportForAdviser($context['adviser'], $context['section'], $context['gradingPeriod'], $officialRoster);

        $this->actingAs($otherAdviser)
            ->get(route('adviser.sections.sf9.download', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'section_roster' => $officialRoster,
                'report_card_record' => $record,
            ]))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.sf9.show', [
                'section' => $context['section'],
                'grading_period' => $otherPeriod,
                'section_roster' => $officialRoster,
            ]))
            ->assertNotFound();

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.sf9.show', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'section_roster' => $unofficialRoster,
            ]))
            ->assertNotFound();
    }

    public function test_preview_uses_only_approved_official_subject_data_and_surfaces_submission_blockers(): void
    {
        $context = $this->createContext();
        $this->createActiveTemplate($context['section']);

        $approvedLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submittedLoad = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Science', 'SCI');
        $approvedSubmission = $this->createSubmission($approvedLoad, $context['gradingPeriod'], GradeSubmissionStatus::Approved);
        $submittedSubmission = $this->createSubmission($submittedLoad, $context['gradingPeriod'], GradeSubmissionStatus::Submitted);

        $officialRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000000111');
        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $approvedSubmission->id,
            'section_roster_id' => $officialRoster->id,
            'grade' => 93,
            'remarks' => 'Passed',
        ]);
        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submittedSubmission->id,
            'section_roster_id' => $officialRoster->id,
            'grade' => 88,
            'remarks' => 'Passed',
        ]);

        $this->actingAs($context['adviser'])
            ->get(route('adviser.sections.sf9.show', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'section_roster' => $officialRoster,
            ]))
            ->assertOk()
            ->assertSeeText('Alicia')
            ->assertSeeText('Mathematics')
            ->assertSeeText('93.00')
            ->assertSeeText('Science is submitted')
            ->assertSeeText('No SF9 export version has been generated yet')
            ->assertSeeText('Version: 1')
            ->assertDontSeeText('88.00');
    }

    public function test_export_is_blocked_without_a_valid_active_template_or_complete_approved_data(): void
    {
        $context = $this->createContext();
        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submission = $this->createSubmission($load, $context['gradingPeriod'], GradeSubmissionStatus::Approved);
        $officialRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000000121');

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $officialRoster->id,
            'grade' => 90,
            'remarks' => 'Passed',
        ]);

        $previewRoute = route('adviser.sections.sf9.show', [
            'section' => $context['section'],
            'grading_period' => $context['gradingPeriod'],
            'section_roster' => $officialRoster,
        ]);

        $this->actingAs($context['adviser'])
            ->get($previewRoute)
            ->assertOk()
            ->assertSeeText('No active SF9 template exists');

        $this->actingAs($context['adviser'])
            ->from($previewRoute)
            ->post(route('adviser.sections.sf9.export', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'section_roster' => $officialRoster,
            ]))
            ->assertRedirect($previewRoute)
            ->assertSessionHasErrors('record');

        $template = $this->createActiveTemplate($context['section']);
        $template->fieldMaps()->where('field_key', 'subject_grade_column')->update(['target_cell' => null]);

        $this->actingAs($context['adviser'])
            ->get($previewRoute)
            ->assertOk()
            ->assertSeeText('incomplete or broken field mappings');
    }

    public function test_sf9_preview_and_export_block_when_the_learner_is_not_grade_eligible_for_the_selected_period(): void
    {
        $context = $this->createContext();
        $context['schoolYear']->update([
            'starts_on' => '2025-06-01',
            'ends_on' => '2026-05-31',
        ]);

        $secondPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $context['schoolYear']->id,
            'quarter' => GradingQuarter::Second,
            'starts_on' => '2025-09-01',
            'ends_on' => '2025-11-15',
            'is_open' => true,
        ]);

        $this->createActiveTemplate($context['section']);

        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submission = $this->createSubmission($load, $secondPeriod, GradeSubmissionStatus::Approved);
        $officialRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000000125');

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $officialRoster->id,
            'grade' => 90,
            'remarks' => 'Passed',
        ]);

        $officialRoster->forceFill([
            'enrollment_status' => EnrollmentStatus::TransferredOut,
            'withdrawn_on' => '2025-11-15',
            'movement_reason' => 'Transferred before the second quarter closed.',
        ])->save();

        $officialRoster->learner->forceFill([
            'enrollment_status' => EnrollmentStatus::TransferredOut,
            'transfer_effective_date' => '2025-11-15',
        ])->save();

        $previewRoute = route('adviser.sections.sf9.show', [
            'section' => $context['section'],
            'grading_period' => $secondPeriod,
            'section_roster' => $officialRoster,
        ]);

        $this->actingAs($context['adviser'])
            ->get($previewRoute)
            ->assertOk()
            ->assertSeeText('Transferred before the second quarter closed.')
            ->assertSeeText('Not required')
            ->assertSeeText('No approved consolidated data exists yet for this learner in the selected grading period.')
            ->assertDontSeeText('90.00');

        $this->actingAs($context['adviser'])
            ->from($previewRoute)
            ->post(route('adviser.sections.sf9.export', [
                'section' => $context['section'],
                'grading_period' => $secondPeriod,
                'section_roster' => $officialRoster,
            ]))
            ->assertRedirect($previewRoute)
            ->assertSessionHasErrors('record');
    }

    public function test_export_creates_a_versioned_report_card_record_from_the_active_sf9_template(): void
    {
        $context = $this->createContext();
        $this->createActiveTemplate($context['section'], [
            'school_name' => 'B2',
            'school_year_name' => 'F2',
            'grading_period_label' => 'H2',
            'grade_level_name' => 'B4',
            'section_name' => 'F4',
            'learner_name' => 'B6',
            'learner_lrn' => 'F6',
            'adviser_name' => 'B8',
            'subject_name_column' => 'A12',
            'subject_grade_column' => 'F12',
            'subject_remarks_column' => 'H12',
            'general_average' => 'F28',
            'promotion_remarks' => 'F30',
        ], version: 2);

        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submission = $this->createSubmission($load, $context['gradingPeriod'], GradeSubmissionStatus::Approved);
        $officialRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000000131');

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $officialRoster->id,
            'grade' => 95,
            'remarks' => 'Passed',
        ]);

        $response = $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf9.export', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'section_roster' => $officialRoster,
            ]));

        $record = ReportCardRecord::query()->latest('id')->firstOrFail();

        $response->assertOk();
        $this->assertDatabaseHas('report_card_records', [
            'id' => $record->id,
            'section_roster_id' => $officialRoster->id,
            'section_id' => $context['section']->id,
            'school_year_id' => $context['schoolYear']->id,
            'learner_id' => $officialRoster->learner_id,
            'grading_period_id' => $context['gradingPeriod']->id,
            'template_version' => 2,
            'record_version' => 1,
            'generated_by' => $context['adviser']->id,
            'is_finalized' => false,
        ]);
        Storage::disk('local')->assertExists($record->file_path);
        $this->assertDatabaseHas('report_card_record_audit_logs', [
            'report_card_record_id' => $record->id,
            'acted_by' => $context['adviser']->id,
            'action' => 'exported',
        ]);

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($record->file_path));
        $worksheet = $spreadsheet->getActiveSheet();

        $this->assertSame('Seamless Grading Demo School', $worksheet->getCell('B2')->getValue());
        $this->assertSame($context['schoolYear']->name, $worksheet->getCell('F2')->getValue());
        $this->assertSame($context['gradingPeriod']->quarter->label(), $worksheet->getCell('H2')->getValue());
        $this->assertSame($context['gradeLevel']->name, $worksheet->getCell('B4')->getValue());
        $this->assertSame($context['section']->name, $worksheet->getCell('F4')->getValue());
        $this->assertStringContainsString('Alicia', (string) $worksheet->getCell('B6')->getValue());
        $this->assertSame('900000000131', preg_replace('/\\.0$/', '', (string) $worksheet->getCell('F6')->getValue()));
        $this->assertSame($context['adviser']->name, $worksheet->getCell('B8')->getValue());
        $this->assertSame('Mathematics', $worksheet->getCell('A12')->getValue());
        $this->assertSame(95.0, (float) $worksheet->getCell('F12')->getValue());
        $this->assertSame('Passed', $worksheet->getCell('H12')->getValue());
        $this->assertSame(95.0, (float) $worksheet->getCell('F28')->getValue());
    }

    public function test_finalization_requires_a_current_export_and_reexport_after_approved_grade_changes(): void
    {
        $context = $this->createContext();
        $this->createActiveTemplate($context['section']);

        $load = $this->createTeacherLoad($context['section'], $context['schoolYear'], 'Mathematics', 'MATH');
        $submission = $this->createSubmission($load, $context['gradingPeriod'], GradeSubmissionStatus::Approved);
        $officialRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000000141');

        $quarterlyGrade = QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $officialRoster->id,
            'grade' => 89,
            'remarks' => 'Passed',
        ]);

        $firstRecord = $this->exportForAdviser($context['adviser'], $context['section'], $context['gradingPeriod'], $officialRoster);

        $quarterlyGrade->update([
            'grade' => 92,
            'remarks' => 'Passed',
        ]);

        $previewRoute = route('adviser.sections.sf9.show', [
            'section' => $context['section'],
            'grading_period' => $context['gradingPeriod'],
            'section_roster' => $officialRoster,
        ]);

        $this->actingAs($context['adviser'])
            ->get($previewRoute)
            ->assertOk()
            ->assertSeeText('no longer matches the current approved data or active template');

        $this->actingAs($context['adviser'])
            ->from($previewRoute)
            ->post(route('adviser.sections.sf9.finalize', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'section_roster' => $officialRoster,
            ]))
            ->assertRedirect($previewRoute)
            ->assertSessionHasErrors('record');

        $secondRecord = $this->exportForAdviser($context['adviser'], $context['section'], $context['gradingPeriod'], $officialRoster);

        $this->actingAs($context['adviser'])
            ->post(route('adviser.sections.sf9.finalize', [
                'section' => $context['section'],
                'grading_period' => $context['gradingPeriod'],
                'section_roster' => $officialRoster,
            ]))
            ->assertRedirect($previewRoute);

        $firstRecord->refresh();
        $secondRecord->refresh();

        $this->assertSame(1, $firstRecord->record_version);
        $this->assertSame(2, $secondRecord->record_version);
        $this->assertFalse($firstRecord->is_finalized);
        $this->assertTrue($secondRecord->is_finalized);
        $this->assertNotNull($secondRecord->finalized_at);
        $this->assertSame($context['adviser']->id, $secondRecord->finalized_by);
        $this->assertDatabaseHas('report_card_record_audit_logs', [
            'report_card_record_id' => $secondRecord->id,
            'acted_by' => $context['adviser']->id,
            'action' => 'finalized',
        ]);

        $this->actingAs($context['adviser'])
            ->get($previewRoute)
            ->assertOk()
            ->assertSeeText('Finalized')
            ->assertSeeText('Version 2')
            ->assertSeeText('Version 1');
    }

    private function createContext(): array
    {
        $adviser = $this->createUserWithRole(RoleName::Adviser->value, ['name' => 'Adviser Review']);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 7']);
        $schoolYear = SchoolYear::factory()->create(['name' => '2042-2043']);
        $section = Section::factory()->create([
            'name' => 'Section Narra',
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
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

    private function createSubmission(
        TeacherLoad $teacherLoad,
        GradingPeriod $gradingPeriod,
        GradeSubmissionStatus $status,
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

    private function createActiveTemplate(
        Section $section,
        array $mappingOverrides = [],
        int $version = 1,
        string $scope = 'grade-level',
    ): Template {
        $scopeGradeLevelId = $scope === 'grade-level' ? $section->grade_level_id : null;
        $scopeKey = $scopeGradeLevelId === null ? 'global' : 'grade-level:'.$scopeGradeLevelId;
        $activeScopeKey = TemplateDocumentType::Sf9->value.':'.$scopeKey;
        $filePath = 'templates/sf9/'.($scope === 'grade-level' ? 'grade-level' : 'global').'-'.$version.'-'.uniqid().'.xlsx';

        $this->storeTemplateWorkbook($filePath);

        $template = Template::factory()->create([
            'code' => $scope === 'grade-level' ? 'sf9-grade-level' : 'sf9-global',
            'name' => $scope === 'grade-level' ? 'Grade-level SF9' : 'Global SF9',
            'document_type' => TemplateDocumentType::Sf9,
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
            'school_year_name' => 'D2',
            'grading_period_label' => 'G2',
            'grade_level_name' => 'A4',
            'section_name' => 'D4',
            'learner_name' => 'A6',
            'learner_lrn' => 'D6',
            'adviser_name' => 'A8',
            'subject_name_column' => 'A12',
            'subject_grade_column' => 'F12',
            'subject_remarks_column' => 'H12',
            'general_average' => 'F28',
            'promotion_remarks' => 'F30',
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
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('SF9');

        $tempPath = tempnam(sys_get_temp_dir(), 'sf9-template');
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

    private function exportForAdviser(
        User $adviser,
        Section $section,
        GradingPeriod $gradingPeriod,
        SectionRoster $sectionRoster,
    ): ReportCardRecord {
        $this->actingAs($adviser)
            ->post(route('adviser.sections.sf9.export', [
                'section' => $section,
                'grading_period' => $gradingPeriod,
                'section_roster' => $sectionRoster,
            ]))
            ->assertOk();

        return ReportCardRecord::query()->latest('id')->firstOrFail();
    }
}
