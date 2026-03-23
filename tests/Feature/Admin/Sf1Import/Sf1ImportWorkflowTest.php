<?php

namespace Tests\Feature\Admin\Sf1Import;

use App\Enums\ImportBatchRowStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\RoleName;
use App\Models\GradeLevel;
use App\Models\ImportBatch;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Sf1ImportWorkflowTest extends TestCase
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

    public function test_admin_can_upload_batch_preview_it_and_only_import_on_confirm(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        [$schoolYear, $section] = $this->makeSectionContext();

        $upload = $this->makeSf1WorkbookUpload([
            [
                'lrn' => '123456789012',
                'last_name' => 'Anderson',
                'first_name' => 'Maria',
                'middle_name' => 'Lopez',
                'suffix' => '',
                'sex' => 'Female',
                'birth_date' => '2012-04-05',
            ],
            [
                'lrn' => '123456789013',
                'last_name' => 'Bautista',
                'first_name' => 'Carlo',
                'middle_name' => 'Diaz',
                'suffix' => '',
                'sex' => 'Male',
                'birth_date' => '2011-11-20',
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('admin.sf1-imports.store'), [
            'section_id' => $section->id,
            'file' => $upload,
        ]);

        $importBatch = ImportBatch::query()->firstOrFail();

        $response->assertRedirect(route('admin.sf1-imports.show', $importBatch));
        $this->assertDatabaseHas('import_batches', [
            'id' => $importBatch->id,
            'status' => ImportBatchStatus::Validated->value,
            'confirmed_at' => null,
            'valid_rows' => 2,
            'invalid_rows' => 0,
        ]);
        Storage::disk('local')->assertExists($importBatch->source_path);
        $this->assertDatabaseCount('learners', 0);
        $this->assertDatabaseCount('section_rosters', 0);

        $this->get(route('admin.sf1-imports.show', $importBatch))
            ->assertOk()
            ->assertSeeText('Confirm import');

        $this->post(route('admin.sf1-imports.confirm', $importBatch))
            ->assertRedirect(route('admin.sf1-imports.show', $importBatch));

        $this->assertDatabaseHas('import_batches', [
            'id' => $importBatch->id,
            'status' => ImportBatchStatus::Confirmed->value,
            'confirmed_by' => $admin->id,
        ]);
        $this->assertDatabaseCount('learners', 2);
        $this->assertDatabaseCount('section_rosters', 2);
        $this->assertDatabaseHas('section_rosters', [
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'is_official' => true,
        ]);
    }

    public function test_preview_surfaces_validation_and_duplicate_issues_and_blocks_confirmation(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        [$schoolYear, $section] = $this->makeSectionContext();
        $otherSection = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $section->grade_level_id,
        ]);

        $rosterLearner = Learner::factory()->create([
            'lrn' => '555555555555',
            'last_name' => 'Rivera',
            'first_name' => 'Paolo',
            'middle_name' => 'Santos',
            'sex' => 'male',
            'birth_date' => '2012-05-01',
        ]);
        SectionRoster::factory()->create([
            'school_year_id' => $schoolYear->id,
            'section_id' => $otherSection->id,
            'learner_id' => $rosterLearner->id,
            'import_batch_id' => null,
        ]);

        Learner::factory()->create([
            'lrn' => '666666666666',
            'last_name' => 'Cruz',
            'first_name' => 'Angela',
            'middle_name' => null,
            'sex' => 'female',
            'birth_date' => '2012-06-15',
        ]);

        $upload = $this->makeSf1WorkbookUpload([
            [
                'lrn' => '111111111111',
                'last_name' => 'Missing',
                'first_name' => '',
                'middle_name' => '',
                'suffix' => '',
                'sex' => 'Male',
                'birth_date' => '2012-01-01',
            ],
            [
                'lrn' => '222222222222',
                'last_name' => 'Duplicate',
                'first_name' => 'Lrn',
                'middle_name' => '',
                'suffix' => '',
                'sex' => 'Female',
                'birth_date' => '2012-02-02',
            ],
            [
                'lrn' => '222222222222',
                'last_name' => 'Duplicate',
                'first_name' => 'Again',
                'middle_name' => '',
                'suffix' => '',
                'sex' => 'Female',
                'birth_date' => '2012-03-03',
            ],
            [
                'lrn' => '777777777777',
                'last_name' => 'Cruz',
                'first_name' => 'Angela',
                'middle_name' => '',
                'suffix' => '',
                'sex' => 'Female',
                'birth_date' => '2012-06-15',
            ],
            [
                'lrn' => '555555555555',
                'last_name' => 'Rivera',
                'first_name' => 'Paolo',
                'middle_name' => 'Santos',
                'suffix' => '',
                'sex' => 'Male',
                'birth_date' => '2012-05-01',
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.sf1-imports.store'), [
            'section_id' => $section->id,
            'file' => $upload,
        ])->assertRedirect();

        $importBatch = ImportBatch::query()->firstOrFail();

        $this->assertDatabaseHas('import_batches', [
            'id' => $importBatch->id,
            'status' => ImportBatchStatus::Validated->value,
        ]);
        $this->assertGreaterThan(0, $importBatch->invalid_rows);

        $this->get(route('admin.sf1-imports.show', $importBatch))
            ->assertOk()
            ->assertSeeText('First name is required.')
            ->assertSeeText('This LRN appears more than once in the uploaded file.')
            ->assertSeeText('A learner record with matching identity already exists.')
            ->assertSeeText('This learner is already assigned to another section in the same school year.');

        $this->from(route('admin.sf1-imports.show', $importBatch))
            ->post(route('admin.sf1-imports.confirm', $importBatch))
            ->assertRedirect(route('admin.sf1-imports.show', $importBatch))
            ->assertSessionHasErrors('record');

        $this->assertDatabaseHas('import_batches', [
            'id' => $importBatch->id,
            'status' => ImportBatchStatus::Validated->value,
            'confirmed_at' => null,
        ]);
    }

    public function test_admin_can_resolve_a_flagged_row_then_confirm_import(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        [$schoolYear, $section] = $this->makeSectionContext();

        $upload = $this->makeSf1WorkbookUpload([
            [
                'lrn' => '999999999999',
                'last_name' => 'Resolve',
                'first_name' => '',
                'middle_name' => '',
                'suffix' => '',
                'sex' => 'Female',
                'birth_date' => '2012-07-20',
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.sf1-imports.store'), [
            'section_id' => $section->id,
            'file' => $upload,
        ])->assertRedirect();

        $importBatch = ImportBatch::query()->firstOrFail();
        $row = $importBatch->rows()->firstOrFail();

        $this->assertDatabaseHas('import_batch_rows', [
            'id' => $row->id,
            'status' => ImportBatchRowStatus::Invalid->value,
        ]);

        $this->put(route('admin.sf1-imports.rows.update', [$importBatch, $row]), [
            'learner_id' => null,
            'lrn' => '999999999999',
            'last_name' => 'Resolve',
            'first_name' => 'Leah',
            'middle_name' => 'Test',
            'suffix' => '',
            'sex' => 'female',
            'birth_date' => '2012-07-20',
        ])->assertRedirect(route('admin.sf1-imports.show', $importBatch));

        $importBatch->refresh();
        $row->refresh();

        $this->assertSame(0, $importBatch->invalid_rows);
        $this->assertSame(1, $importBatch->valid_rows);
        $this->assertSame(ImportBatchRowStatus::Valid, $row->status);

        $this->post(route('admin.sf1-imports.confirm', $importBatch))
            ->assertRedirect(route('admin.sf1-imports.show', $importBatch));

        $this->assertDatabaseHas('learners', [
            'lrn' => '999999999999',
            'first_name' => 'Leah',
        ]);
        $this->assertDatabaseHas('section_rosters', [
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
        ]);
        $this->assertDatabaseHas('import_batch_rows', [
            'id' => $row->id,
            'status' => ImportBatchRowStatus::Imported->value,
        ]);
    }

    public function test_confirm_updates_existing_learner_and_roster_in_same_section_safely(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        [$schoolYear, $section] = $this->makeSectionContext();

        $learner = Learner::factory()->create([
            'lrn' => '888888888888',
            'last_name' => 'Lopez',
            'first_name' => 'Marco',
            'middle_name' => 'Reyes',
            'sex' => 'male',
            'birth_date' => '2012-08-08',
        ]);
        $roster = SectionRoster::factory()->create([
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'learner_id' => $learner->id,
            'is_official' => false,
            'import_batch_id' => null,
        ]);

        $upload = $this->makeSf1WorkbookUpload([
            [
                'lrn' => '888888888888',
                'last_name' => 'Lopez',
                'first_name' => 'Marco',
                'middle_name' => 'Reyes',
                'suffix' => '',
                'sex' => 'Male',
                'birth_date' => '2012-08-08',
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.sf1-imports.store'), [
            'section_id' => $section->id,
            'file' => $upload,
        ])->assertRedirect();

        $importBatch = ImportBatch::query()->firstOrFail();

        $this->post(route('admin.sf1-imports.confirm', $importBatch))
            ->assertRedirect(route('admin.sf1-imports.show', $importBatch));

        $this->assertDatabaseCount('learners', 1);
        $this->assertDatabaseCount('section_rosters', 1);
        $this->assertDatabaseHas('section_rosters', [
            'id' => $roster->id,
            'import_batch_id' => $importBatch->id,
            'is_official' => true,
        ]);
    }

    public function test_non_admin_users_cannot_access_sf1_import_routes(): void
    {
        [$schoolYear, $section] = $this->makeSectionContext();
        $importBatch = ImportBatch::factory()->create([
            'section_id' => $section->id,
        ]);
        $importBatch->rows()->create([
            'row_number' => 2,
            'payload' => [
                'lrn' => '123456789012',
                'last_name' => 'Locked',
                'first_name' => 'User',
                'sex' => 'Male',
                'birth_date' => '2012-01-01',
            ],
            'normalized_data' => [
                'lrn' => '123456789012',
                'last_name' => 'Locked',
                'first_name' => 'User',
                'sex' => 'male',
                'birth_date' => '2012-01-01',
            ],
            'status' => ImportBatchRowStatus::Invalid,
            'errors' => [['code' => 'missing_field', 'message' => 'First name is required.']],
        ]);
        $row = $importBatch->rows()->firstOrFail();

        $getRoutes = [
            route('admin.sf1-imports.index'),
            route('admin.sf1-imports.create'),
            route('admin.sf1-imports.show', $importBatch),
            route('admin.sf1-imports.rows.edit', [$importBatch, $row]),
        ];

        foreach ([RoleName::Teacher, RoleName::Adviser, RoleName::Registrar] as $role) {
            $user = $this->createUserWithRole($role->value);
            $this->actingAs($user);

            foreach ($getRoutes as $route) {
                $this->get($route)->assertRedirect(route('access.denied'));
            }

            $this->post(route('admin.sf1-imports.store'), [
                'section_id' => $section->id,
                'file' => $this->makeSf1WorkbookUpload([
                    [
                        'lrn' => '123456789012',
                        'last_name' => 'Blocked',
                        'first_name' => 'Write',
                        'middle_name' => '',
                        'suffix' => '',
                        'sex' => 'Male',
                        'birth_date' => '2012-01-01',
                    ],
                ]),
            ])->assertForbidden();

            $this->post(route('admin.sf1-imports.confirm', $importBatch))->assertForbidden();
        }
    }

    public function test_sf10_workbook_is_rejected_from_sf1_import_flow_with_a_clear_message(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        [, $section] = $this->makeSectionContext();

        $response = $this->actingAs($admin)->post(route('admin.sf1-imports.store'), [
            'section_id' => $section->id,
            'file' => $this->makeFixtureUpload(
                'School-Form-10-ES-Learners-Academic Permanent-Record_26March2025.xlsx',
            ),
        ]);

        $response
            ->assertSessionHasErrors('file')
            ->assertSessionHasErrors([
                'file' => 'Detected a DepEd SF10-ES template workbook. SF10 templates are not learner roster import files; upload this workbook through template management instead.',
            ]);

        $this->assertDatabaseHas('import_batches', [
            'status' => ImportBatchStatus::Failed->value,
        ]);
        $this->assertDatabaseCount('import_batch_rows', 0);
    }

    public function test_card_workbook_is_rejected_from_sf1_import_flow_with_a_clear_message(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        [, $section] = $this->makeSectionContext();

        $response = $this->actingAs($admin)->post(route('admin.sf1-imports.store'), [
            'section_id' => $section->id,
            'file' => $this->makeFixtureUpload('CARD 1.xlsx'),
        ]);

        $response
            ->assertSessionHasErrors('file')
            ->assertSessionHasErrors([
                'file' => 'Detected a DepEd CARD template workbook. CARD templates are not learner roster import files; upload this workbook through template management instead.',
            ]);

        $this->assertDatabaseHas('import_batches', [
            'status' => ImportBatchStatus::Failed->value,
        ]);
        $this->assertDatabaseCount('import_batch_rows', 0);
    }

    private function makeSectionContext(): array
    {
        $schoolYear = SchoolYear::factory()->create();
        $gradeLevel = GradeLevel::factory()->create();
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
        ]);

        return [$schoolYear, $section];
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeSf1WorkbookUpload(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('school_form_1_shs_ver2018.2.1.1');

        $sheet->setCellValue('H3', 'School Form 1 School Register for Senior High School (SF1-SHS)');
        $sheet->setCellValue('F5', 'School Name');
        $sheet->setCellValue('I5', 'Seamless Grading National High School');
        $sheet->setCellValue('P5', 'School ID');
        $sheet->setCellValue('S5', '302614');
        $sheet->setCellValue('G9', 'Semester');
        $sheet->setCellValue('I9', 'First Semester');
        $sheet->setCellValue('P9', 'School Year');
        $sheet->setCellValue('S9', '2025 - 2026');
        $sheet->setCellValue('G16', 'Section');
        $sheet->setCellValue('I16', 'Molave');
        $sheet->setCellValue('A18', 'LRN');
        $sheet->setCellValue('C18', 'NAME (Last Name, First Name, Name Extension, Middle Name)');
        $sheet->setCellValue('K18', 'Sex (M/F)');
        $sheet->setCellValue('L18', 'BIRTH DATE (mm/dd/yyyy)');
        $sheet->setCellValue('O18', 'Age as of October 31');
        $sheet->setCellValue('Q18', 'Religious Affilication');
        $sheet->setCellValue('U18', 'COMPLETE ADDRESS');
        $sheet->setCellValue('U19', 'House #/ Street/ Sitio/ Purok');
        $sheet->setCellValue('Z19', 'Barangay');
        $sheet->setCellValue('AE19', 'Municipality/ City');
        $sheet->setCellValue('AG19', 'Province');
        $sheet->setCellValue('AK18', 'PARENTS');
        $sheet->setCellValue('AK19', "Father's Name (Last Name, First Name, Middle Name)");
        $sheet->setCellValue('AP19', "Mother's Maiden Name (Last Name, First Name, Middle Name)");
        $sheet->setCellValue('AR18', 'GUARDIAN (if learner is not Living with Parent)');
        $sheet->setCellValue('AR19', 'Name (Last Name, First Name, Name Extension, Middle');
        $sheet->setCellValue('AV19', 'Relationship');
        $sheet->setCellValue('AX18', 'Contact Number of Parent or Guardian');
        $sheet->setCellValue('BA18', 'Learning Modality');
        $sheet->setCellValue('BC18', 'REMARKS');
        $sheet->setCellValue('BC19', '(Please refer to the legend on last page)');

        foreach ($rows as $rowIndex => $row) {
            $sheetRow = $rowIndex + 20;
            $name = collect([
                $row['last_name'] ?? '',
                implode(' ', array_filter([
                    $row['first_name'] ?? '',
                    $row['suffix'] ?? '',
                    $row['middle_name'] ?? '',
                ])),
            ])->implode(',');

            $sheet->setCellValue('A'.$sheetRow, $row['lrn'] ?? '');
            $sheet->setCellValue('C'.$sheetRow, $name);
            $sheet->setCellValue('K'.$sheetRow, strtoupper(substr((string) ($row['sex'] ?? ''), 0, 1)));
            $sheet->setCellValue('L'.$sheetRow, $row['birth_date'] ?? '');
            $sheet->setCellValue('O'.$sheetRow, $row['age'] ?? 13);
            $sheet->setCellValue('Q'.$sheetRow, $row['religion'] ?? 'Christianity');
            $sheet->setCellValue('BA'.$sheetRow, $row['learning_modality'] ?? 'Face to Face');
            $sheet->setCellValue('BC'.$sheetRow, $row['remarks'] ?? '');
        }

        $legendRow = count($rows) + 20;
        $sheet->setCellValue('A'.$legendRow, 'Legend: List and Code of Indicators under REMARKS column');

        $tempPath = tempnam(sys_get_temp_dir(), 'sf1-import');
        $xlsxPath = $tempPath.'.xlsx';

        rename($tempPath, $xlsxPath);

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);

        return new UploadedFile(
            $xlsxPath,
            'sf1-import.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function makeFixtureUpload(string $fixtureName): UploadedFile
    {
        $path = base_path('tests/Fixtures/DepEd/'.$fixtureName);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return new UploadedFile(
            $path,
            $fixtureName,
            match (strtolower($extension)) {
                'xls' => 'application/vnd.ms-excel',
                default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            },
            null,
            true,
        );
    }
}
