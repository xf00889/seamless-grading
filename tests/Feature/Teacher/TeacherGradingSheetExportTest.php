<?php

namespace Tests\Feature\Teacher;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Enums\RoleName;
use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\GradingSheetExport;
use App\Models\Learner;
use App\Models\QuarterlyGrade;
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
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TeacherGradingSheetExportTest extends TestCase
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
    }

    public function test_teacher_can_only_open_owned_preview_export_and_download_routes_and_mismatched_periods_404(): void
    {
        $context = $this->createContext();
        $this->createActiveTemplate($context['teacherLoad']);
        $submission = $this->createSubmission($context['teacherLoad'], $context['gradingPeriod'], GradeSubmissionStatus::Submitted);
        $roster = $this->createRoster($context['section'], $context['schoolYear'], 'Owned', '900000000001');

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $roster->id,
            'grade' => 87,
            'remarks' => 'Passed',
        ]);

        $otherTeacher = $this->createUserWithRole(RoleName::Teacher->value);
        $otherSchoolYear = SchoolYear::factory()->create();
        $otherPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $otherSchoolYear->id,
            'quarter' => GradingQuarter::Second,
        ]);

        $this->actingAs($context['teacher'])
            ->get(route('teacher.grading-sheet.show', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertOk()
            ->assertSeeText('Grading Sheet Preview');

        $this->actingAs($otherTeacher)
            ->get(route('teacher.grading-sheet.show', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($otherTeacher)
            ->post(route('teacher.grading-sheet.export', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertForbidden();

        $export = $this->exportForTeacher($context['teacher'], $context['teacherLoad'], $context['gradingPeriod']);

        $this->actingAs($otherTeacher)
            ->get(route('teacher.grading-sheet.download', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $context['gradingPeriod'],
                'grading_sheet_export' => $export,
            ]))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($context['teacher'])
            ->get(route('teacher.grading-sheet.show', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $otherPeriod,
            ]))
            ->assertNotFound();
    }

    public function test_preview_shows_only_persisted_official_rows_and_current_submission_status(): void
    {
        $context = $this->createContext();
        $this->createActiveTemplate($context['teacherLoad']);
        $submission = $this->createSubmission(
            $context['teacherLoad'],
            $context['gradingPeriod'],
            GradeSubmissionStatus::Returned,
            'Please verify the class standing totals.',
        );

        $officialOne = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000000011');
        $officialTwo = $this->createRoster($context['section'], $context['schoolYear'], 'Marco', '900000000012');
        $this->createRoster($context['section'], $context['schoolYear'], 'Hidden', '900000000013', [
            'is_official' => false,
        ]);
        $otherSection = Section::factory()->create([
            'school_year_id' => $context['schoolYear']->id,
            'grade_level_id' => $context['gradeLevel']->id,
            'adviser_id' => $context['adviser']->id,
        ]);
        $this->createRoster($otherSection, $context['schoolYear'], 'Other', '900000000014');

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $officialOne->id,
            'grade' => 91.5,
            'remarks' => 'Passed',
        ]);
        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $officialTwo->id,
            'grade' => 74,
            'remarks' => 'Failed',
        ]);

        $this->actingAs($context['teacher'])
            ->get(route('teacher.grading-sheet.show', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertOk()
            ->assertSeeText('Returned')
            ->assertSeeText('Please verify the class standing totals.')
            ->assertSeeText('Alicia')
            ->assertSeeText('Marco')
            ->assertSeeText('91.50')
            ->assertSeeText('74.00')
            ->assertDontSeeText('Hidden')
            ->assertDontSeeText('Other')
            ->assertSeeText('Returned for correction');
    }

    public function test_preview_and_export_are_blocked_without_active_template_or_saved_submission(): void
    {
        $context = $this->createContext();
        $previewRoute = route('teacher.grading-sheet.show', [
            'teacher_load' => $context['teacherLoad'],
            'grading_period' => $context['gradingPeriod'],
        ]);

        $this->actingAs($context['teacher'])
            ->get($previewRoute)
            ->assertOk()
            ->assertSeeText('No active grading-sheet template exists for this load')
            ->assertSeeText('No saved grade submission exists yet for this teacher load and grading period')
            ->assertSeeText('Blocked');

        $this->actingAs($context['teacher'])
            ->from($previewRoute)
            ->post(route('teacher.grading-sheet.export', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertRedirect($previewRoute)
            ->assertSessionHasErrors('record');
    }

    public function test_preview_and_export_are_blocked_when_active_template_is_broken_or_saved_rows_are_inconsistent(): void
    {
        $context = $this->createContext();
        $template = $this->createActiveTemplate($context['teacherLoad'], [
            'learner_grade_column' => 'J12',
        ]);
        $submission = $this->createSubmission($context['teacherLoad'], $context['gradingPeriod'], GradeSubmissionStatus::Submitted);
        $officialRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Valid', '900000000021');

        $otherSection = Section::factory()->create([
            'school_year_id' => $context['schoolYear']->id,
            'grade_level_id' => $context['gradeLevel']->id,
            'adviser_id' => $context['adviser']->id,
        ]);
        $rogueRoster = $this->createRoster($otherSection, $context['schoolYear'], 'Rogue', '900000000022');

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $officialRoster->id,
            'grade' => 88,
            'remarks' => 'Passed',
        ]);
        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $rogueRoster->id,
            'grade' => 90,
            'remarks' => 'Passed',
        ]);

        $template->fieldMaps()->where('field_key', 'teacher_name')->update(['target_cell' => null]);

        $previewRoute = route('teacher.grading-sheet.show', [
            'teacher_load' => $context['teacherLoad'],
            'grading_period' => $context['gradingPeriod'],
        ]);

        $this->actingAs($context['teacher'])
            ->get($previewRoute)
            ->assertOk()
            ->assertSeeText('incomplete or broken field mappings')
            ->assertSeeText('Saved grade rows do not match the official roster');

        $this->actingAs($context['teacher'])
            ->from($previewRoute)
            ->post(route('teacher.grading-sheet.export', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertRedirect($previewRoute)
            ->assertSessionHasErrors('record');
    }

    public function test_export_creates_a_versioned_history_record_and_uses_the_active_template_scope_and_mappings(): void
    {
        $context = $this->createContext();
        $this->createActiveTemplate($context['teacherLoad'], [
            'school_year_name' => 'D3',
            'grading_period_label' => 'H3',
            'grade_level_name' => 'D5',
            'section_name' => 'H5',
            'subject_name' => 'D7',
            'teacher_name' => 'H7',
            'adviser_name' => 'D9',
            'learner_name_column' => 'B12',
            'learner_lrn_column' => 'F12',
            'learner_sex_column' => 'H12',
            'learner_grade_column' => 'J12',
            'learner_remarks_column' => 'L12',
        ], version: 3, scope: 'grade-level');
        $globalTemplate = $this->createActiveTemplate($context['teacherLoad'], [
            'school_year_name' => 'A1',
        ], version: 1, scope: 'global');
        $submission = $this->createSubmission($context['teacherLoad'], $context['gradingPeriod'], GradeSubmissionStatus::Submitted);
        $firstRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000000031');
        $secondRoster = $this->createRoster($context['section'], $context['schoolYear'], 'Marco', '900000000032');

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $firstRoster->id,
            'grade' => 91.5,
            'remarks' => 'Passed',
        ]);
        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $secondRoster->id,
            'grade' => 74,
            'remarks' => 'Failed',
        ]);

        $response = $this->actingAs($context['teacher'])
            ->post(route('teacher.grading-sheet.export', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $context['gradingPeriod'],
            ]));

        $export = GradingSheetExport::query()->latest('id')->firstOrFail();

        $response->assertOk();
        $this->assertDatabaseHas('grading_sheet_exports', [
            'id' => $export->id,
            'teacher_load_id' => $context['teacherLoad']->id,
            'grading_period_id' => $context['gradingPeriod']->id,
            'template_id' => $export->template_id,
            'template_version' => 3,
            'version' => 1,
            'exported_by' => $context['teacher']->id,
        ]);
        $this->assertNotEquals($globalTemplate->id, $export->template_id);
        Storage::disk('local')->assertExists($export->file_path);
        $this->assertDatabaseHas('grading_sheet_export_audit_logs', [
            'grading_sheet_export_id' => $export->id,
            'acted_by' => $context['teacher']->id,
            'action' => 'exported',
        ]);

        $spreadsheet = IOFactory::load(Storage::disk('local')->path($export->file_path));
        $worksheet = $spreadsheet->getActiveSheet();

        $this->assertSame($context['schoolYear']->name, $worksheet->getCell('D3')->getValue());
        $this->assertSame($context['gradingPeriod']->quarter->label(), $worksheet->getCell('H3')->getValue());
        $this->assertSame($context['gradeLevel']->name, $worksheet->getCell('D5')->getValue());
        $this->assertSame($context['section']->name, $worksheet->getCell('H5')->getValue());
        $this->assertSame($context['subject']->name, $worksheet->getCell('D7')->getValue());
        $this->assertSame($context['teacher']->name, $worksheet->getCell('H7')->getValue());
        $this->assertSame($context['adviser']->name, $worksheet->getCell('D9')->getValue());
        $this->assertStringContainsString('Alicia', (string) $worksheet->getCell('B12')->getValue());
        $this->assertStringContainsString('Marco', (string) $worksheet->getCell('B13')->getValue());
        $this->assertSame('900000000031', preg_replace('/\.0$/', '', (string) $worksheet->getCell('F12')->getValue()));
        $this->assertSame('900000000032', preg_replace('/\.0$/', '', (string) $worksheet->getCell('F13')->getValue()));
        $this->assertSame('Female', $worksheet->getCell('H12')->getValue());
        $this->assertSame('Female', $worksheet->getCell('H13')->getValue());
        $this->assertSame(91.5, (float) $worksheet->getCell('J12')->getValue());
        $this->assertSame(74.0, (float) $worksheet->getCell('J13')->getValue());
        $this->assertSame('Passed', $worksheet->getCell('L12')->getValue());
        $this->assertSame('Failed', $worksheet->getCell('L13')->getValue());
    }

    public function test_export_after_grade_changes_creates_a_new_version_and_preserves_prior_history(): void
    {
        $context = $this->createContext();
        $this->createActiveTemplate($context['teacherLoad']);
        $submission = $this->createSubmission($context['teacherLoad'], $context['gradingPeriod'], GradeSubmissionStatus::Approved);
        $roster = $this->createRoster($context['section'], $context['schoolYear'], 'Alicia', '900000000041');

        $quarterlyGrade = QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $roster->id,
            'grade' => 80,
            'remarks' => 'Passed',
        ]);

        $firstExport = $this->exportForTeacher($context['teacher'], $context['teacherLoad'], $context['gradingPeriod']);

        $quarterlyGrade->update([
            'grade' => 85,
            'remarks' => 'Passed',
        ]);

        $secondExport = $this->exportForTeacher($context['teacher'], $context['teacherLoad'], $context['gradingPeriod']);

        $this->assertSame(1, $firstExport->version);
        $this->assertSame(2, $secondExport->version);
        $this->assertNotSame($firstExport->file_path, $secondExport->file_path);
        Storage::disk('local')->assertExists($firstExport->file_path);
        Storage::disk('local')->assertExists($secondExport->file_path);

        $firstWorkbook = IOFactory::load(Storage::disk('local')->path($firstExport->file_path));
        $secondWorkbook = IOFactory::load(Storage::disk('local')->path($secondExport->file_path));

        $this->assertSame(80.0, (float) $firstWorkbook->getActiveSheet()->getCell('D12')->getValue());
        $this->assertSame(85.0, (float) $secondWorkbook->getActiveSheet()->getCell('D12')->getValue());

        $this->actingAs($context['teacher'])
            ->get(route('teacher.grading-sheet.show', [
                'teacher_load' => $context['teacherLoad'],
                'grading_period' => $context['gradingPeriod'],
            ]))
            ->assertOk()
            ->assertSeeText('Version 2')
            ->assertSeeText('Version 1');
    }

    private function createContext(): array
    {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value, ['name' => 'Teacher Export']);
        $adviser = $this->createUserWithRole(RoleName::Adviser->value, ['name' => 'Adviser Review']);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 7']);
        $schoolYear = SchoolYear::factory()->create(['name' => '2041-2042']);
        $section = Section::factory()->create([
            'name' => 'Section Narra',
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $subject = Subject::factory()->create([
            'name' => 'Mathematics',
            'code' => 'MATH-7',
        ]);
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

        return compact('teacher', 'adviser', 'gradeLevel', 'schoolYear', 'section', 'subject', 'teacherLoad', 'gradingPeriod');
    }

    private function createActiveTemplate(
        TeacherLoad $teacherLoad,
        array $mappingOverrides = [],
        int $version = 1,
        string $scope = 'global',
    ): Template {
        $scopeGradeLevelId = $scope === 'grade-level' ? $teacherLoad->section->grade_level_id : null;
        $scopeKey = $scopeGradeLevelId === null ? 'global' : 'grade-level:'.$scopeGradeLevelId;
        $activeScopeKey = TemplateDocumentType::GradingSheet->value.':'.$scopeKey;
        $filePath = 'templates/grading_sheet/'.($scope === 'grade-level' ? 'grade-level' : 'global').'-'.$version.'-'.uniqid().'.xlsx';

        $this->storeTemplateWorkbook($filePath);

        $template = Template::factory()->create([
            'code' => $scope === 'grade-level' ? 'grading-sheet-grade-level' : 'grading-sheet-global',
            'name' => $scope === 'grade-level' ? 'Grade-level Grading Sheet' : 'Global Grading Sheet',
            'document_type' => TemplateDocumentType::GradingSheet,
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
            'school_year_name' => 'A3',
            'grading_period_label' => 'D3',
            'grade_level_name' => 'A5',
            'section_name' => 'D5',
            'subject_name' => 'A7',
            'teacher_name' => 'D7',
            'adviser_name' => 'A9',
            'learner_name_column' => 'A12',
            'learner_lrn_column' => 'B12',
            'learner_sex_column' => 'C12',
            'learner_grade_column' => 'D12',
            'learner_remarks_column' => 'E12',
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
        $worksheet->setTitle('Grading Sheet');

        $tempPath = tempnam(sys_get_temp_dir(), 'grading-sheet-template');
        $xlsxPath = $tempPath.'.xlsx';

        rename($tempPath, $xlsxPath);

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);

        Storage::disk('local')->put($filePath, file_get_contents($xlsxPath));

        @unlink($xlsxPath);
    }

    private function createSubmission(
        TeacherLoad $teacherLoad,
        GradingPeriod $gradingPeriod,
        GradeSubmissionStatus $status,
        ?string $remarks = null,
    ): GradeSubmission {
        return GradeSubmission::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
            'grading_period_id' => $gradingPeriod->id,
            'status' => $status,
            'submitted_by' => $teacherLoad->teacher_id,
            'submitted_at' => in_array($status, [GradeSubmissionStatus::Submitted, GradeSubmissionStatus::Returned, GradeSubmissionStatus::Approved, GradeSubmissionStatus::Locked], true)
                ? now()->subDay()
                : null,
            'returned_at' => $status === GradeSubmissionStatus::Returned ? now()->subHours(4) : null,
            'approved_at' => $status === GradeSubmissionStatus::Approved ? now()->subHours(2) : null,
            'locked_at' => $status === GradeSubmissionStatus::Locked ? now()->subHour() : null,
            'adviser_remarks' => $remarks,
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

    private function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function exportForTeacher(User $teacher, TeacherLoad $teacherLoad, GradingPeriod $gradingPeriod): GradingSheetExport
    {
        $this->actingAs($teacher)
            ->post(route('teacher.grading-sheet.export', [
                'teacher_load' => $teacherLoad,
                'grading_period' => $gradingPeriod,
            ]))
            ->assertOk();

        return GradingSheetExport::query()->latest('id')->firstOrFail();
    }
}
