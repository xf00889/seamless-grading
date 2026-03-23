<?php

namespace Database\Seeders;

use App\Enums\ApprovalAction;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Enums\GradingSheetExportAuditAction;
use App\Enums\ImportBatchRowStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\LearnerSex;
use App\Enums\LearnerStatusAuditAction;
use App\Enums\LearnerYearEndStatus;
use App\Enums\ReportCardRecordAuditAction;
use App\Enums\RoleName;
use App\Enums\TemplateAuditAction;
use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\GradingSheetExport;
use App\Models\ImportBatch;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\Subject;
use App\Models\SystemSetting;
use App\Models\TeacherLoad;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UatDemoSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $users = $this->seedUsers();
        $references = $this->resolveReferenceData();
        $schoolYears = $this->seedSchoolYears();
        $periods = $this->seedGradingPeriods($schoolYears);
        $templates = $this->seedTemplates($references['grade7'], $users['admin']);
        $sections = $this->seedSections($schoolYears, $references, $users);
        $learners = $this->seedLearners();
        $importBatch = $this->seedConfirmedSf1Batch($sections['narra'], $users['admin'], $learners['narra']);
        $rosters = $this->seedRosters($schoolYears, $sections, $users, $learners, $importBatch);
        $loads = $this->seedTeacherLoads($schoolYears['current'], $sections, $references['subjects'], $users);

        $this->seedQuarterlyWorkflowData($periods, $loads, $rosters, $users);
        $this->seedCurrentGradingSheetExports($loads['narra_mathematics'], $periods['current_q2'], $templates['grading_sheet'], $users['teacher']);
        $this->seedSf9Records($periods, $sections, $rosters, $templates['sf9'], $users);
        $this->seedSf10Records($periods['previous_q4'], $sections['acacia'], $rosters['previous'], $templates['sf10'], $users);
        $this->seedSystemSettings($schoolYears['current'], $periods['current_q2'], $templates);
    }

    private function seedUsers(): array
    {
        $password = Hash::make(self::DEMO_PASSWORD);

        $users = [
            'admin' => ['name' => 'Ava Administrator', 'email' => 'admin.uat@example.test', 'role' => RoleName::Admin],
            'teacher' => ['name' => 'Tomas Teacher', 'email' => 'teacher.uat@example.test', 'role' => RoleName::Teacher],
            'teacher_support' => ['name' => 'Nina Support Teacher', 'email' => 'teacher.support@example.test', 'role' => RoleName::Teacher],
            'adviser' => ['name' => 'Alicia Adviser', 'email' => 'adviser.uat@example.test', 'role' => RoleName::Adviser],
            'registrar' => ['name' => 'Rina Registrar', 'email' => 'registrar.uat@example.test', 'role' => RoleName::Registrar],
            'adviser_support' => ['name' => 'Miguel Support Adviser', 'email' => 'adviser.support@example.test', 'role' => RoleName::Adviser],
        ];

        $seededUsers = [];

        foreach ($users as $key => $attributes) {
            $user = User::query()->updateOrCreate(
                ['email' => $attributes['email']],
                [
                    'name' => $attributes['name'],
                    'email_verified_at' => now(),
                    'password' => $password,
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$attributes['role']->value]);
            $seededUsers[$key] = $user;
        }

        return $seededUsers;
    }

    private function resolveReferenceData(): array
    {
        return [
            'grade6' => GradeLevel::query()->where('code', 'GRADE-6')->firstOrFail(),
            'grade7' => GradeLevel::query()->where('code', 'GRADE-7')->firstOrFail(),
            'subjects' => Subject::query()
                ->whereIn('code', ['MAT', 'ENG', 'SCI', 'AP', 'ESP', 'MAPEH', 'TLE'])
                ->get()
                ->keyBy('code'),
        ];
    }

    private function seedSchoolYears(): array
    {
        SchoolYear::query()->update(['is_active' => false]);

        $previous = SchoolYear::query()->updateOrCreate(
            ['name' => '2024-2025'],
            ['starts_on' => '2024-06-01', 'ends_on' => '2025-05-31', 'is_active' => false],
        );

        $current = SchoolYear::query()->updateOrCreate(
            ['name' => '2025-2026'],
            ['starts_on' => '2025-06-01', 'ends_on' => '2026-05-31', 'is_active' => true],
        );

        return ['previous' => $previous, 'current' => $current];
    }

    private function seedGradingPeriods(array $schoolYears): array
    {
        $definitions = [
            ['quarter' => GradingQuarter::First, 'starts_on' => '06-01', 'ends_on' => '08-31'],
            ['quarter' => GradingQuarter::Second, 'starts_on' => '09-01', 'ends_on' => '10-31'],
            ['quarter' => GradingQuarter::Third, 'starts_on' => '11-01', 'ends_on' => '01-31'],
            ['quarter' => GradingQuarter::Fourth, 'starts_on' => '02-01', 'ends_on' => '04-30'],
        ];

        $seededPeriods = [];

        foreach ($schoolYears as $key => $schoolYear) {
            GradingPeriod::query()->where('school_year_id', $schoolYear->id)->update(['is_open' => false]);

            [$startYear, $endYear] = explode('-', $schoolYear->name);

            foreach ($definitions as $definition) {
                $quarter = $definition['quarter'];
                $startsOn = $definition['starts_on'];
                $endsOn = $definition['ends_on'];

                $period = GradingPeriod::query()->updateOrCreate(
                    ['school_year_id' => $schoolYear->id, 'quarter' => $quarter],
                    [
                        'starts_on' => sprintf(
                            '%s-%s',
                            $quarter === GradingQuarter::Third || $quarter === GradingQuarter::Fourth ? $endYear : $startYear,
                            $startsOn,
                        ),
                        'ends_on' => sprintf(
                            '%s-%s',
                            $quarter === GradingQuarter::First || $quarter === GradingQuarter::Second ? $startYear : $endYear,
                            $endsOn,
                        ),
                        'is_open' => $key === 'current' && $quarter === GradingQuarter::Second,
                    ],
                );

                $seededPeriods[sprintf('%s_q%d', $key, $quarter->value)] = $period;
            }
        }

        return $seededPeriods;
    }

    private function seedTemplates(GradeLevel $grade7, User $admin): array
    {
        $gradingSheet = Template::query()->where('document_type', TemplateDocumentType::GradingSheet)->where('code', 'grading-sheet')->where('version', 1)->firstOrFail();
        $sf9 = Template::query()->where('document_type', TemplateDocumentType::Sf9)->where('code', 'report-card')->where('version', 1)->firstOrFail();
        $sf10 = Template::query()->where('document_type', TemplateDocumentType::Sf10)->where('code', 'report-card')->where('version', 1)->firstOrFail();

        $gradingSheet->forceFill([
            'name' => 'Quarterly Grading Sheet',
            'description' => 'UAT-ready grading sheet template for teacher preview and export validation.',
            'grade_level_id' => null,
            'scope_key' => 'global',
            'active_scope_key' => 'grading_sheet:global',
            'file_path' => 'templates/grading_sheet/grading-sheet-v1.xlsx',
            'file_disk' => 'local',
            'is_active' => true,
            'activated_at' => '2025-06-03 08:00:00',
            'deactivated_at' => null,
        ])->save();

        $sf9->forceFill([
            'name' => 'SF9 Report Card',
            'description' => 'UAT-ready SF9 template for Grade 7 quarterly finalization checks.',
            'grade_level_id' => $grade7->id,
            'scope_key' => 'grade-level:'.$grade7->id,
            'active_scope_key' => 'sf9:grade-level:'.$grade7->id,
            'file_path' => 'templates/sf9/report-card-v1.xlsx',
            'file_disk' => 'local',
            'is_active' => true,
            'activated_at' => '2025-06-03 08:10:00',
            'deactivated_at' => null,
        ])->save();

        $sf10->forceFill([
            'name' => 'SF10 Permanent Record',
            'description' => 'UAT-ready SF10 template for year-end preparation and registrar handoff checks.',
            'grade_level_id' => null,
            'scope_key' => 'global',
            'active_scope_key' => 'sf10:global',
            'file_path' => 'templates/sf10/report-card-v1.xlsx',
            'file_disk' => 'local',
            'is_active' => true,
            'activated_at' => '2025-06-03 08:20:00',
            'deactivated_at' => null,
        ])->save();

        foreach ([$gradingSheet, $sf9, $sf10] as $template) {
            $this->storeSpreadsheet($template->file_path, [
                [$template->name, 'Version '.$template->version],
                ['Document type', $template->document_type->label()],
                ['Scope', $template->scope_key],
                ['Field key', 'Target cell'],
                ...$template->fieldMaps()->orderBy('field_key')->get()->map(
                    fn ($fieldMap): array => [$fieldMap->field_key, $fieldMap->target_cell ?? ''],
                )->all(),
            ]);

            $template->auditLogs()->delete();
            $template->auditLogs()->createMany([
                [
                    'acted_by' => $admin->id,
                    'action' => TemplateAuditAction::Uploaded,
                    'remarks' => 'Seeded UAT workbook uploaded for release-readiness verification.',
                    'metadata' => [
                        'module' => 'template-management',
                        'document_type' => $template->document_type->value,
                        'scope_key' => $template->scope_key,
                        'template_version' => $template->version,
                    ],
                    'created_at' => $template->activated_at?->subMinutes(10) ?? now()->subMinutes(10),
                    'updated_at' => $template->activated_at?->subMinutes(10) ?? now()->subMinutes(10),
                ],
                [
                    'acted_by' => $admin->id,
                    'action' => TemplateAuditAction::Activated,
                    'remarks' => 'Activated for the UAT dataset.',
                    'metadata' => [
                        'module' => 'template-management',
                        'document_type' => $template->document_type->value,
                        'scope_key' => $template->scope_key,
                        'template_version' => $template->version,
                    ],
                    'created_at' => $template->activated_at ?? now(),
                    'updated_at' => $template->activated_at ?? now(),
                ],
            ]);
        }

        return ['grading_sheet' => $gradingSheet, 'sf9' => $sf9, 'sf10' => $sf10];
    }

    private function seedSections(array $schoolYears, array $references, array $users): array
    {
        $previous = Section::query()->updateOrCreate(
            ['school_year_id' => $schoolYears['previous']->id, 'name' => 'Acacia'],
            ['grade_level_id' => $references['grade6']->id, 'adviser_id' => $users['adviser']->id, 'is_active' => true],
        );

        $narra = Section::query()->updateOrCreate(
            ['school_year_id' => $schoolYears['current']->id, 'name' => 'Narra'],
            ['grade_level_id' => $references['grade7']->id, 'adviser_id' => $users['adviser']->id, 'is_active' => true],
        );

        $molave = Section::query()->updateOrCreate(
            ['school_year_id' => $schoolYears['current']->id, 'name' => 'Molave'],
            ['grade_level_id' => $references['grade7']->id, 'adviser_id' => $users['adviser_support']->id, 'is_active' => true],
        );

        return ['acacia' => $previous, 'narra' => $narra, 'molave' => $molave];
    }

    private function seedLearners(): array
    {
        $definitions = [
            'narra' => [
                'maria' => [
                    'lrn' => '202500000001',
                    'last_name' => 'Lopez',
                    'first_name' => 'Maria',
                    'middle_name' => 'Reyes',
                    'sex' => LearnerSex::Female,
                    'birth_date' => '2012-04-05',
                    'enrollment_status' => EnrollmentStatus::Active,
                    'transfer_effective_date' => null,
                ],
                'carlo' => [
                    'lrn' => '202500000002',
                    'last_name' => 'Bautista',
                    'first_name' => 'Carlo',
                    'middle_name' => 'Diaz',
                    'sex' => LearnerSex::Male,
                    'birth_date' => '2012-09-14',
                    'enrollment_status' => EnrollmentStatus::Active,
                    'transfer_effective_date' => null,
                ],
                'leah' => [
                    'lrn' => '202500000003',
                    'last_name' => 'Mendoza',
                    'first_name' => 'Leah',
                    'middle_name' => 'Santos',
                    'sex' => LearnerSex::Female,
                    'birth_date' => '2012-07-20',
                    'enrollment_status' => EnrollmentStatus::Active,
                    'transfer_effective_date' => null,
                ],
                'paolo' => [
                    'lrn' => '202500000004',
                    'last_name' => 'Rivera',
                    'first_name' => 'Paolo',
                    'middle_name' => 'Garcia',
                    'sex' => LearnerSex::Male,
                    'birth_date' => '2012-03-11',
                    'enrollment_status' => EnrollmentStatus::Active,
                    'transfer_effective_date' => null,
                ],
                'angela' => [
                    'lrn' => '202500000005',
                    'last_name' => 'Cruz',
                    'first_name' => 'Angela',
                    'middle_name' => 'Morales',
                    'sex' => LearnerSex::Female,
                    'birth_date' => '2012-06-15',
                    'enrollment_status' => EnrollmentStatus::TransferredOut,
                    'transfer_effective_date' => '2025-10-15',
                ],
                'jonah' => [
                    'lrn' => '202500000006',
                    'last_name' => 'Santos',
                    'first_name' => 'Jonah',
                    'middle_name' => 'Flores',
                    'sex' => LearnerSex::Male,
                    'birth_date' => '2012-01-24',
                    'enrollment_status' => EnrollmentStatus::Dropped,
                    'transfer_effective_date' => null,
                ],
            ],
            'molave' => [
                'rosa' => [
                    'lrn' => '202500000007',
                    'last_name' => 'Velasco',
                    'first_name' => 'Rosa',
                    'middle_name' => 'Pineda',
                    'sex' => LearnerSex::Female,
                    'birth_date' => '2012-05-19',
                    'enrollment_status' => EnrollmentStatus::Active,
                    'transfer_effective_date' => null,
                ],
                'daniel' => [
                    'lrn' => '202500000008',
                    'last_name' => 'Flores',
                    'first_name' => 'Daniel',
                    'middle_name' => 'Aquino',
                    'sex' => LearnerSex::Male,
                    'birth_date' => '2012-02-28',
                    'enrollment_status' => EnrollmentStatus::Active,
                    'transfer_effective_date' => null,
                ],
            ],
        ];

        $seededLearners = [];

        foreach ($definitions as $group => $learners) {
            foreach ($learners as $key => $attributes) {
                $seededLearners[$group][$key] = Learner::query()->updateOrCreate(
                    ['lrn' => $attributes['lrn']],
                    $attributes,
                );
            }
        }

        return $seededLearners;
    }

    private function seedConfirmedSf1Batch(Section $section, User $admin, array $learners): ImportBatch
    {
        $sourcePath = sprintf('imports/uat/%s-sf1-%s.xlsx', $section->name, str_replace('-', '', $section->schoolYear->name));

        $this->storeSpreadsheet($sourcePath, [
            ['lrn', 'last_name', 'first_name', 'middle_name', 'suffix', 'sex', 'birth_date'],
            ...collect($learners)->map(fn (Learner $learner): array => [
                $learner->lrn,
                $learner->last_name,
                $learner->first_name,
                $learner->middle_name ?? '',
                $learner->suffix ?? '',
                $learner->sex->label(),
                optional($learner->birth_date)->format('Y-m-d') ?? '',
            ])->values()->all(),
        ]);

        $batch = ImportBatch::query()->updateOrCreate(
            [
                'section_id' => $section->id,
                'source_file_name' => 'sf1-narra-2025-2026.xlsx',
            ],
            [
                'imported_by' => $admin->id,
                'status' => ImportBatchStatus::Confirmed,
                'source_disk' => 'local',
                'source_path' => $sourcePath,
                'total_rows' => count($learners),
                'valid_rows' => count($learners),
                'invalid_rows' => 0,
                'confirmed_at' => '2025-06-10 09:30:00',
                'confirmed_by' => $admin->id,
            ],
        );

        $batch->rows()->delete();

        foreach (array_values($learners) as $index => $learner) {
            $batch->rows()->create([
                'learner_id' => $learner->id,
                'row_number' => $index + 2,
                'payload' => [
                    'lrn' => $learner->lrn,
                    'last_name' => $learner->last_name,
                    'first_name' => $learner->first_name,
                    'middle_name' => $learner->middle_name,
                    'sex' => $learner->sex->value,
                    'birth_date' => optional($learner->birth_date)->format('Y-m-d'),
                ],
                'normalized_data' => [
                    'lrn' => $learner->lrn,
                    'last_name' => $learner->last_name,
                    'first_name' => $learner->first_name,
                    'middle_name' => $learner->middle_name,
                    'sex' => $learner->sex->value,
                    'birth_date' => optional($learner->birth_date)->format('Y-m-d'),
                ],
                'errors' => null,
                'status' => ImportBatchRowStatus::Imported,
            ]);
        }

        return $batch;
    }

    private function seedRosters(
        array $schoolYears,
        array $sections,
        array $users,
        array $learners,
        ImportBatch $importBatch,
    ): array {
        $currentNarra = [];

        foreach ($learners['narra'] as $key => $learner) {
            $currentNarra[$key] = SectionRoster::query()->updateOrCreate(
                [
                    'school_year_id' => $schoolYears['current']->id,
                    'learner_id' => $learner->id,
                ],
                [
                    'section_id' => $sections['narra']->id,
                    'import_batch_id' => $importBatch->id,
                    'enrollment_status' => match ($key) {
                        'angela' => EnrollmentStatus::TransferredOut,
                        'jonah' => EnrollmentStatus::Dropped,
                        default => EnrollmentStatus::Active,
                    },
                    'enrolled_on' => '2025-06-10',
                    'withdrawn_on' => match ($key) {
                        'angela' => '2025-10-15',
                        'jonah' => '2025-10-20',
                        default => null,
                    },
                    'movement_reason' => match ($key) {
                        'angela' => 'Transferred to Ridgeview Integrated School after family relocation.',
                        'jonah' => 'Dropped due to prolonged non-attendance and parent-requested withdrawal.',
                        default => null,
                    },
                    'movement_recorded_at' => match ($key) {
                        'angela' => '2025-10-15 14:10:00',
                        'jonah' => '2025-10-20 16:30:00',
                        default => null,
                    },
                    'movement_recorded_by' => match ($key) {
                        'angela', 'jonah' => $users['adviser']->id,
                        default => null,
                    },
                    'year_end_status' => null,
                    'year_end_status_reason' => null,
                    'year_end_status_set_at' => null,
                    'year_end_status_set_by' => null,
                    'is_official' => true,
                ],
            );
        }

        $molave = [];

        foreach ($learners['molave'] as $key => $learner) {
            $molave[$key] = SectionRoster::query()->updateOrCreate(
                [
                    'school_year_id' => $schoolYears['current']->id,
                    'learner_id' => $learner->id,
                ],
                [
                    'section_id' => $sections['molave']->id,
                    'import_batch_id' => null,
                    'enrollment_status' => EnrollmentStatus::Active,
                    'enrolled_on' => '2025-06-11',
                    'withdrawn_on' => null,
                    'movement_reason' => null,
                    'movement_recorded_at' => null,
                    'movement_recorded_by' => null,
                    'year_end_status' => null,
                    'year_end_status_reason' => null,
                    'year_end_status_set_at' => null,
                    'year_end_status_set_by' => null,
                    'is_official' => true,
                ],
            );
        }

        $previous = [
            'maria' => SectionRoster::query()->updateOrCreate(
                [
                    'school_year_id' => $schoolYears['previous']->id,
                    'learner_id' => $learners['narra']['maria']->id,
                ],
                [
                    'section_id' => $sections['acacia']->id,
                    'import_batch_id' => null,
                    'enrollment_status' => EnrollmentStatus::Active,
                    'enrolled_on' => '2024-06-10',
                    'withdrawn_on' => null,
                    'movement_reason' => null,
                    'movement_recorded_at' => null,
                    'movement_recorded_by' => null,
                    'year_end_status' => LearnerYearEndStatus::Promoted,
                    'year_end_status_reason' => null,
                    'year_end_status_set_at' => '2025-04-29 10:15:00',
                    'year_end_status_set_by' => $users['adviser']->id,
                    'is_official' => true,
                ],
            ),
            'carlo' => SectionRoster::query()->updateOrCreate(
                [
                    'school_year_id' => $schoolYears['previous']->id,
                    'learner_id' => $learners['narra']['carlo']->id,
                ],
                [
                    'section_id' => $sections['acacia']->id,
                    'import_batch_id' => null,
                    'enrollment_status' => EnrollmentStatus::Active,
                    'enrolled_on' => '2024-06-10',
                    'withdrawn_on' => null,
                    'movement_reason' => null,
                    'movement_recorded_at' => null,
                    'movement_recorded_by' => null,
                    'year_end_status' => LearnerYearEndStatus::Retained,
                    'year_end_status_reason' => 'General average below the promotion threshold.',
                    'year_end_status_set_at' => '2025-04-29 10:20:00',
                    'year_end_status_set_by' => $users['adviser']->id,
                    'is_official' => true,
                ],
            ),
        ];

        $this->syncLearnerMovementAudits($sections, $schoolYears, $users, $currentNarra, $previous);

        return [
            'current' => $currentNarra,
            'molave' => $molave,
            'previous' => $previous,
        ];
    }

    private function syncLearnerMovementAudits(
        array $sections,
        array $schoolYears,
        array $users,
        array $currentNarra,
        array $previous,
    ): void {
        $entries = [
            [
                'roster' => $currentNarra['angela'],
                'action' => LearnerStatusAuditAction::TransferredOutMarked,
                'remarks' => 'Transfer-out recorded for UAT exception handling.',
                'metadata' => [
                    'module' => 'learner-movement',
                    'section' => $sections['narra']->name,
                    'school_year' => $schoolYears['current']->name,
                    'effective_date' => '2025-10-15',
                ],
                'created_at' => '2025-10-15 14:10:00',
            ],
            [
                'roster' => $currentNarra['jonah'],
                'action' => LearnerStatusAuditAction::DroppedMarked,
                'remarks' => 'Dropout recorded for UAT exception handling.',
                'metadata' => [
                    'module' => 'learner-movement',
                    'section' => $sections['narra']->name,
                    'school_year' => $schoolYears['current']->name,
                    'effective_date' => '2025-10-20',
                    'reason' => 'Prolonged non-attendance and parent-requested withdrawal.',
                ],
                'created_at' => '2025-10-20 16:30:00',
            ],
            [
                'roster' => $previous['maria'],
                'action' => LearnerStatusAuditAction::YearEndStatusUpdated,
                'remarks' => 'Year-end status set to promoted for archived final records.',
                'metadata' => [
                    'module' => 'year-end-status',
                    'section' => $sections['acacia']->name,
                    'school_year' => $schoolYears['previous']->name,
                    'year_end_status' => LearnerYearEndStatus::Promoted->value,
                ],
                'created_at' => '2025-04-29 10:15:00',
            ],
            [
                'roster' => $previous['carlo'],
                'action' => LearnerStatusAuditAction::YearEndStatusUpdated,
                'remarks' => 'Year-end status set to retained for archived final records.',
                'metadata' => [
                    'module' => 'year-end-status',
                    'section' => $sections['acacia']->name,
                    'school_year' => $schoolYears['previous']->name,
                    'year_end_status' => LearnerYearEndStatus::Retained->value,
                    'reason' => 'General average below the promotion threshold.',
                ],
                'created_at' => '2025-04-29 10:20:00',
            ],
        ];

        foreach ($entries as $entry) {
            $entry['roster']->learnerStatusAuditLogs()->delete();
            $entry['roster']->learnerStatusAuditLogs()->create([
                'acted_by' => $users['adviser']->id,
                'action' => $entry['action'],
                'remarks' => $entry['remarks'],
                'metadata' => $entry['metadata'],
                'created_at' => $entry['created_at'],
                'updated_at' => $entry['created_at'],
            ]);
        }
    }

    private function seedTeacherLoads(SchoolYear $currentSchoolYear, array $sections, $subjects, array $users): array
    {
        $loads = [
            'narra_mathematics' => ['section_id' => $sections['narra']->id, 'subject_id' => $subjects['MAT']->id, 'teacher_id' => $users['teacher']->id],
            'narra_english' => ['section_id' => $sections['narra']->id, 'subject_id' => $subjects['ENG']->id, 'teacher_id' => $users['teacher']->id],
            'narra_science' => ['section_id' => $sections['narra']->id, 'subject_id' => $subjects['SCI']->id, 'teacher_id' => $users['teacher']->id],
            'narra_araling_panlipunan' => ['section_id' => $sections['narra']->id, 'subject_id' => $subjects['AP']->id, 'teacher_id' => $users['teacher']->id],
            'narra_esp' => ['section_id' => $sections['narra']->id, 'subject_id' => $subjects['ESP']->id, 'teacher_id' => $users['teacher_support']->id],
            'narra_mapeh' => ['section_id' => $sections['narra']->id, 'subject_id' => $subjects['MAPEH']->id, 'teacher_id' => $users['teacher_support']->id],
            'narra_tle' => ['section_id' => $sections['narra']->id, 'subject_id' => $subjects['TLE']->id, 'teacher_id' => $users['teacher_support']->id],
            'molave_mathematics' => ['section_id' => $sections['molave']->id, 'subject_id' => $subjects['MAT']->id, 'teacher_id' => $users['teacher']->id],
            'molave_english' => ['section_id' => $sections['molave']->id, 'subject_id' => $subjects['ENG']->id, 'teacher_id' => $users['teacher_support']->id],
        ];

        $seededLoads = [];

        foreach ($loads as $key => $attributes) {
            $seededLoads[$key] = TeacherLoad::query()->updateOrCreate(
                [
                    'teacher_id' => $attributes['teacher_id'],
                    'school_year_id' => $currentSchoolYear->id,
                    'section_id' => $attributes['section_id'],
                    'subject_id' => $attributes['subject_id'],
                ],
                ['is_active' => true],
            );
        }

        return $seededLoads;
    }

    private function seedQuarterlyWorkflowData(array $periods, array $loads, array $rosters, array $users): void
    {
        $currentRosters = array_values($rosters['current']);
        $eligibleCurrentRosters = array_values(array_filter(
            $rosters['current'],
            fn (SectionRoster $roster): bool => $roster->enrollment_status === EnrollmentStatus::Active,
        ));
        $molaveRosters = array_values($rosters['molave']);
        $previousRosters = array_values($rosters['previous']);

        foreach ([
            'narra_mathematics',
            'narra_english',
            'narra_science',
            'narra_araling_panlipunan',
            'narra_esp',
            'narra_mapeh',
            'narra_tle',
        ] as $loadKey) {
            $this->syncSubmission(
                $loads[$loadKey],
                $periods['current_q1'],
                GradeSubmissionStatus::Approved,
                $currentRosters,
                $loads[$loadKey]->teacher,
                [
                    ['acted_by' => $loads[$loadKey]->teacher_id, 'action' => ApprovalAction::DraftSaved, 'remarks' => 'Q1 grades seeded for UAT.'],
                    ['acted_by' => $loads[$loadKey]->teacher_id, 'action' => ApprovalAction::Submitted, 'remarks' => 'Submitted for adviser review.'],
                    ['acted_by' => $users['adviser']->id, 'action' => ApprovalAction::Approved, 'remarks' => 'Approved for official SF9 generation.'],
                ],
                null,
                [
                    'submitted_at' => '2025-08-25 13:00:00',
                    'approved_at' => '2025-08-27 09:15:00',
                ],
            );
        }

        $this->syncSubmission(
            $loads['narra_mathematics'],
            $periods['current_q2'],
            GradeSubmissionStatus::Approved,
            $eligibleCurrentRosters,
            $users['teacher'],
            [
                ['acted_by' => $users['teacher']->id, 'action' => ApprovalAction::DraftSaved, 'remarks' => 'Second-quarter grades saved for review.'],
                ['acted_by' => $users['teacher']->id, 'action' => ApprovalAction::Submitted, 'remarks' => 'Submitted on time for adviser review.'],
                ['acted_by' => $users['adviser']->id, 'action' => ApprovalAction::Approved, 'remarks' => 'Approved and ready for export.'],
            ],
            null,
            [
                'submitted_at' => '2025-10-25 10:10:00',
                'approved_at' => '2025-10-27 11:45:00',
            ],
        );

        $this->syncSubmission(
            $loads['narra_english'],
            $periods['current_q2'],
            GradeSubmissionStatus::Draft,
            $eligibleCurrentRosters,
            $users['teacher'],
            [
                ['acted_by' => $users['teacher']->id, 'action' => ApprovalAction::DraftSaved, 'remarks' => 'Saved mid-entry for UAT teacher testing.'],
            ],
            null,
            [],
            [
                $eligibleCurrentRosters[0]->id => [
                    ['changed_by' => $users['teacher']->id, 'previous_grade' => 86, 'new_grade' => 88, 'reason' => 'Adjusted after notebook recheck.'],
                ],
            ],
        );

        $this->syncSubmission(
            $loads['narra_science'],
            $periods['current_q2'],
            GradeSubmissionStatus::Submitted,
            $eligibleCurrentRosters,
            $users['teacher'],
            [
                ['acted_by' => $users['teacher']->id, 'action' => ApprovalAction::DraftSaved, 'remarks' => 'Draft saved before final submission.'],
                ['acted_by' => $users['teacher']->id, 'action' => ApprovalAction::Submitted, 'remarks' => 'Awaiting adviser review.'],
            ],
            null,
            [
                'submitted_at' => '2025-11-03 18:05:00',
            ],
        );

        $this->syncSubmission(
            $loads['narra_tle'],
            $periods['current_q2'],
            GradeSubmissionStatus::Returned,
            $eligibleCurrentRosters,
            $users['teacher_support'],
            [
                ['acted_by' => $users['teacher_support']->id, 'action' => ApprovalAction::DraftSaved, 'remarks' => 'Initial TLE draft saved.'],
                ['acted_by' => $users['teacher_support']->id, 'action' => ApprovalAction::Submitted, 'remarks' => 'Submitted for adviser review.'],
                ['acted_by' => $users['adviser']->id, 'action' => ApprovalAction::Returned, 'remarks' => 'Please attach the corrected performance-task basis for Jonah and Angela exclusions.'],
            ],
            'Please attach the corrected performance-task basis for Jonah and Angela exclusions.',
            [
                'submitted_at' => '2025-11-02 17:20:00',
                'returned_at' => '2025-11-04 09:30:00',
            ],
        );

        $this->syncSubmission(
            $loads['narra_esp'],
            $periods['current_q2'],
            GradeSubmissionStatus::Returned,
            $eligibleCurrentRosters,
            $users['teacher_support'],
            [
                ['acted_by' => $users['teacher_support']->id, 'action' => ApprovalAction::DraftSaved, 'remarks' => 'Initial ESP draft saved.'],
                ['acted_by' => $users['teacher_support']->id, 'action' => ApprovalAction::Submitted, 'remarks' => 'Submitted for adviser approval.'],
                ['acted_by' => $users['adviser']->id, 'action' => ApprovalAction::Approved, 'remarks' => 'Originally approved before quarter lock.'],
                ['acted_by' => $users['admin']->id, 'action' => ApprovalAction::Locked, 'remarks' => 'Locked during quarter closeout check.'],
                ['acted_by' => $users['admin']->id, 'action' => ApprovalAction::Reopened, 'remarks' => 'Reopened after a late correction request.'],
            ],
            'Reopened by admin after a late correction request. Resubmit for adviser approval after fixing the conduct grade basis.',
            [
                'submitted_at' => '2025-10-29 08:35:00',
                'returned_at' => '2025-11-06 15:15:00',
            ],
            [
                $eligibleCurrentRosters[1]->id => [
                    ['changed_by' => $users['teacher_support']->id, 'previous_grade' => 90, 'new_grade' => 92, 'reason' => 'Adjusted after verified values from conduct journal.'],
                ],
            ],
        );

        $this->syncSubmission(
            $loads['narra_mapeh'],
            $periods['current_q2'],
            GradeSubmissionStatus::Locked,
            $eligibleCurrentRosters,
            $users['teacher_support'],
            [
                ['acted_by' => $users['teacher_support']->id, 'action' => ApprovalAction::DraftSaved, 'remarks' => 'Draft saved before adviser review.'],
                ['acted_by' => $users['teacher_support']->id, 'action' => ApprovalAction::Submitted, 'remarks' => 'Submitted for adviser review.'],
                ['acted_by' => $users['adviser']->id, 'action' => ApprovalAction::Approved, 'remarks' => 'Approved before quarter lock.'],
                ['acted_by' => $users['admin']->id, 'action' => ApprovalAction::Locked, 'remarks' => 'Quarter locked after adviser completion.'],
            ],
            null,
            [
                'submitted_at' => '2025-10-24 16:45:00',
                'approved_at' => '2025-10-26 11:10:00',
                'locked_at' => '2025-11-01 08:00:00',
            ],
        );

        $this->syncSubmission(
            $loads['molave_mathematics'],
            $periods['current_q2'],
            GradeSubmissionStatus::Approved,
            $molaveRosters,
            $users['teacher'],
            [
                ['acted_by' => $users['teacher']->id, 'action' => ApprovalAction::DraftSaved, 'remarks' => 'Molave math draft saved.'],
                ['acted_by' => $users['teacher']->id, 'action' => ApprovalAction::Submitted, 'remarks' => 'Molave math submitted.'],
                ['acted_by' => $users['adviser_support']->id, 'action' => ApprovalAction::Approved, 'remarks' => 'Molave math approved.'],
            ],
            null,
            [
                'submitted_at' => '2025-10-23 14:10:00',
                'approved_at' => '2025-10-24 09:00:00',
            ],
        );

        $this->syncSubmission(
            $loads['molave_english'],
            $periods['current_q2'],
            GradeSubmissionStatus::Approved,
            $molaveRosters,
            $users['teacher_support'],
            [
                ['acted_by' => $users['teacher_support']->id, 'action' => ApprovalAction::DraftSaved, 'remarks' => 'Molave English draft saved.'],
                ['acted_by' => $users['teacher_support']->id, 'action' => ApprovalAction::Submitted, 'remarks' => 'Molave English submitted.'],
                ['acted_by' => $users['adviser_support']->id, 'action' => ApprovalAction::Approved, 'remarks' => 'Molave English approved.'],
            ],
            null,
            [
                'submitted_at' => '2025-10-22 11:30:00',
                'approved_at' => '2025-10-24 09:20:00',
            ],
        );

        foreach ([['subject' => 'MAT'], ['subject' => 'ENG']] as $index => $definition) {
            $teacherLoad = TeacherLoad::query()->updateOrCreate(
                [
                    'teacher_id' => $users['teacher']->id,
                    'school_year_id' => $previousRosters[0]->school_year_id,
                    'section_id' => $previousRosters[0]->section_id,
                    'subject_id' => Subject::query()->where('code', $definition['subject'])->firstOrFail()->id,
                ],
                ['is_active' => true],
            );

            $this->syncSubmission(
                $teacherLoad,
                $periods['previous_q4'],
                GradeSubmissionStatus::Approved,
                $previousRosters,
                $users['teacher'],
                [
                    ['acted_by' => $users['teacher']->id, 'action' => ApprovalAction::DraftSaved, 'remarks' => 'Year-end draft saved.'],
                    ['acted_by' => $users['teacher']->id, 'action' => ApprovalAction::Submitted, 'remarks' => 'Year-end grades submitted.'],
                    ['acted_by' => $users['adviser']->id, 'action' => ApprovalAction::Approved, 'remarks' => 'Year-end grades approved for SF10 preparation.'],
                ],
                null,
                [
                    'submitted_at' => '2025-04-20 15:20:00',
                    'approved_at' => '2025-04-24 09:35:00',
                ],
            );
        }
    }

    private function seedCurrentGradingSheetExports(
        TeacherLoad $teacherLoad,
        GradingPeriod $gradingPeriod,
        Template $template,
        User $teacher,
    ): void {
        foreach ([
            [
                'version' => 1,
                'file_name' => 'narra-mathematics-q2-v1.xlsx',
                'file_path' => 'exports/grading-sheets/narra-mathematics-q2-v1.xlsx',
                'exported_at' => '2025-10-27 12:00:00',
                'remarks' => 'Initial teacher copy after adviser approval.',
            ],
            [
                'version' => 2,
                'file_name' => 'narra-mathematics-q2-v2.xlsx',
                'file_path' => 'exports/grading-sheets/narra-mathematics-q2-v2.xlsx',
                'exported_at' => '2025-10-28 08:30:00',
                'remarks' => 'Regenerated after the approved copy was reviewed in UAT.',
            ],
        ] as $definition) {
            $export = GradingSheetExport::query()->updateOrCreate(
                [
                    'teacher_load_id' => $teacherLoad->id,
                    'grading_period_id' => $gradingPeriod->id,
                    'version' => $definition['version'],
                ],
                [
                    'template_id' => $template->id,
                    'exported_by' => $teacher->id,
                    'template_version' => $template->version,
                    'file_name' => $definition['file_name'],
                    'file_disk' => 'local',
                    'file_path' => $definition['file_path'],
                    'exported_at' => $definition['exported_at'],
                ],
            );

            $this->storeSpreadsheet($definition['file_path'], [
                ['Teacher load', $teacherLoad->section->name.' - '.$teacherLoad->subject->name],
                ['School year', $teacherLoad->schoolYear->name],
                ['Grading period', $gradingPeriod->quarter->label()],
                ['Export version', $definition['version']],
            ]);

            $export->auditLogs()->delete();
            $export->auditLogs()->create([
                'acted_by' => $teacher->id,
                'action' => GradingSheetExportAuditAction::Exported,
                'remarks' => $definition['remarks'],
                'metadata' => [
                    'module' => 'grading-sheet-export',
                    'template_version' => $template->version,
                    'export_version' => $definition['version'],
                    'teacher_load_id' => $teacherLoad->id,
                    'grading_period_id' => $gradingPeriod->id,
                ],
                'created_at' => $definition['exported_at'],
                'updated_at' => $definition['exported_at'],
            ]);
        }
    }

    private function seedSf9Records(array $periods, array $sections, array $rosters, Template $template, array $users): void
    {
        foreach (array_values($rosters['current']) as $roster) {
            $this->syncReportCardRecord(
                $roster,
                $periods['current_q1'],
                $template,
                TemplateDocumentType::Sf9,
                [
                    'record_version' => 1,
                    'generated_by' => $users['adviser']->id,
                    'template_version' => $template->version,
                    'file_name' => sprintf('sf9-%s-q1-v1.xlsx', $roster->learner->lrn),
                    'file_disk' => 'local',
                    'file_path' => sprintf('exports/sf9/%s-q1-v1.xlsx', $roster->learner->lrn),
                    'is_finalized' => true,
                    'generated_at' => '2025-08-28 10:00:00',
                    'finalized_at' => '2025-08-28 10:15:00',
                    'finalized_by' => $users['adviser']->id,
                    'payload' => [
                        'source_hash' => 'sf9-'.$roster->learner->lrn.'-q1',
                        'general_average' => 88.25,
                        'remarks' => 'Quarter complete',
                    ],
                ],
                [
                    [
                        'acted_by' => $users['adviser']->id,
                        'action' => ReportCardRecordAuditAction::Exported,
                        'remarks' => 'Quarterly SF9 generated for UAT review.',
                        'metadata' => ['module' => 'sf9', 'template_version' => $template->version, 'record_version' => 1],
                        'created_at' => '2025-08-28 10:00:00',
                    ],
                    [
                        'acted_by' => $users['adviser']->id,
                        'action' => ReportCardRecordAuditAction::Finalized,
                        'remarks' => 'Quarterly SF9 finalized for official adviser record checks.',
                        'metadata' => ['module' => 'sf9', 'template_version' => $template->version, 'record_version' => 1],
                        'created_at' => '2025-08-28 10:15:00',
                    ],
                ],
            );
        }

        foreach (array_values($rosters['molave']) as $roster) {
            $this->syncReportCardRecord(
                $roster,
                $periods['current_q2'],
                $template,
                TemplateDocumentType::Sf9,
                [
                    'record_version' => 1,
                    'generated_by' => $users['adviser_support']->id,
                    'template_version' => $template->version,
                    'file_name' => sprintf('sf9-%s-q2-v1.xlsx', $roster->learner->lrn),
                    'file_disk' => 'local',
                    'file_path' => sprintf('exports/sf9/%s-q2-v1.xlsx', $roster->learner->lrn),
                    'is_finalized' => true,
                    'generated_at' => '2025-10-28 13:15:00',
                    'finalized_at' => '2025-10-28 13:35:00',
                    'finalized_by' => $users['adviser_support']->id,
                    'payload' => [
                        'source_hash' => 'sf9-'.$roster->learner->lrn.'-q2',
                        'general_average' => 90.50,
                        'remarks' => 'Ready for registrar verification.',
                    ],
                ],
                [
                    [
                        'acted_by' => $users['adviser_support']->id,
                        'action' => ReportCardRecordAuditAction::Exported,
                        'remarks' => 'Completed-section SF9 generated for UAT monitoring.',
                        'metadata' => ['module' => 'sf9', 'template_version' => $template->version, 'record_version' => 1],
                        'created_at' => '2025-10-28 13:15:00',
                    ],
                    [
                        'acted_by' => $users['adviser_support']->id,
                        'action' => ReportCardRecordAuditAction::Finalized,
                        'remarks' => 'Completed-section SF9 finalized.',
                        'metadata' => ['module' => 'sf9', 'template_version' => $template->version, 'record_version' => 1],
                        'created_at' => '2025-10-28 13:35:00',
                    ],
                ],
            );
        }
    }

    private function seedSf10Records(
        GradingPeriod $gradingPeriod,
        Section $section,
        array $rosters,
        Template $template,
        array $users,
    ): void {
        foreach ($rosters as $key => $roster) {
            $remarks = $key === 'maria' ? 'Promoted to Grade 7.' : 'Retained in Grade 6 for reinforcement.';

            $this->syncReportCardRecord(
                $roster,
                $gradingPeriod,
                $template,
                TemplateDocumentType::Sf10,
                [
                    'record_version' => 1,
                    'generated_by' => $users['adviser']->id,
                    'template_version' => $template->version,
                    'file_name' => sprintf('sf10-%s-v1.xlsx', $roster->learner->lrn),
                    'file_disk' => 'local',
                    'file_path' => sprintf('exports/sf10/%s-v1.xlsx', $roster->learner->lrn),
                    'is_finalized' => true,
                    'generated_at' => '2025-04-30 11:00:00',
                    'finalized_at' => '2025-04-30 11:20:00',
                    'finalized_by' => $users['adviser']->id,
                    'payload' => [
                        'source_hash' => 'sf10-'.$roster->learner->lrn.'-'.$section->schoolYear->name,
                        'general_average' => $key === 'maria' ? 90.10 : 76.75,
                        'remarks' => $remarks,
                        'year_end_status' => $roster->year_end_status?->value,
                    ],
                ],
                [
                    [
                        'acted_by' => $users['adviser']->id,
                        'action' => ReportCardRecordAuditAction::Exported,
                        'remarks' => 'SF10 draft exported for year-end preparation.',
                        'metadata' => ['module' => 'sf10', 'template_version' => $template->version, 'record_version' => 1],
                        'created_at' => '2025-04-30 11:00:00',
                    ],
                    [
                        'acted_by' => $users['adviser']->id,
                        'action' => ReportCardRecordAuditAction::Finalized,
                        'remarks' => 'SF10 finalized for registrar repository handoff.',
                        'metadata' => ['module' => 'sf10', 'template_version' => $template->version, 'record_version' => 1],
                        'created_at' => '2025-04-30 11:20:00',
                    ],
                ],
            );
        }
    }

    private function seedSystemSettings(SchoolYear $schoolYear, GradingPeriod $gradingPeriod, array $templates): void
    {
        $settings = [
            'school.profile' => [
                'value' => ['name' => 'Seamless Grading Academy', 'division' => 'Demo Division', 'campus_code' => 'SGA-UAT'],
                'description' => 'UAT school profile defaults.',
                'is_public' => true,
            ],
            'academic.active_school_year' => [
                'value' => ['school_year_id' => $schoolYear->id],
                'description' => 'Active school year for UAT walkthroughs.',
                'is_public' => false,
            ],
            'academic.open_grading_period' => [
                'value' => ['grading_period_id' => $gradingPeriod->id],
                'description' => 'Current quarter reference for UAT walkthroughs.',
                'is_public' => false,
            ],
            'templates.active.sf9' => [
                'value' => ['template_id' => $templates['sf9']->id, 'version' => $templates['sf9']->version],
                'description' => 'Active SF9 template for UAT.',
                'is_public' => false,
            ],
            'templates.active.grading_sheet' => [
                'value' => ['template_id' => $templates['grading_sheet']->id, 'version' => $templates['grading_sheet']->version],
                'description' => 'Active grading sheet template for UAT.',
                'is_public' => false,
            ],
        ];

        foreach ($settings as $key => $setting) {
            SystemSetting::query()->updateOrCreate(['key' => $key], $setting);
        }
    }

    private function syncSubmission(
        TeacherLoad $teacherLoad,
        GradingPeriod $gradingPeriod,
        GradeSubmissionStatus $status,
        array $rosters,
        User $submittedBy,
        array $logs,
        ?string $adviserRemarks = null,
        array $timestamps = [],
        array $gradeChangeLogs = [],
    ): GradeSubmission {
        $submission = GradeSubmission::query()->updateOrCreate(
            [
                'teacher_load_id' => $teacherLoad->id,
                'grading_period_id' => $gradingPeriod->id,
            ],
            [
                'status' => $status,
                'submitted_by' => $status === GradeSubmissionStatus::Draft
                    ? null
                    : $submittedBy->id,
                'adviser_remarks' => $adviserRemarks,
                'submitted_at' => $timestamps['submitted_at'] ?? null,
                'returned_at' => $timestamps['returned_at'] ?? null,
                'approved_at' => $timestamps['approved_at'] ?? null,
                'locked_at' => $timestamps['locked_at'] ?? null,
            ],
        );

        $baseGrade = match ($teacherLoad->subject->code) {
            'MAT' => 89,
            'ENG' => 87,
            'SCI' => 88,
            'AP' => 86,
            'ESP' => 91,
            'MAPEH' => 90,
            'TLE' => 92,
            default => 85,
        };

        $rosterIds = array_map(fn (SectionRoster $roster): int => $roster->id, $rosters);
        $submission->quarterlyGrades()->whereNotIn('section_roster_id', $rosterIds)->delete();

        foreach (array_values($rosters) as $index => $roster) {
            $quarterlyGrade = $submission->quarterlyGrades()->updateOrCreate(
                ['section_roster_id' => $roster->id],
                [
                    'grade' => $baseGrade + ($index % 4),
                    'remarks' => $status === GradeSubmissionStatus::Returned ? 'Needs review' : 'Passed',
                ],
            );

            $quarterlyGrade->gradeChangeLogs()->delete();

            foreach ($gradeChangeLogs[$roster->id] ?? [] as $changeLog) {
                $quarterlyGrade->gradeChangeLogs()->create($changeLog);
            }
        }

        $submission->approvalLogs()->delete();

        foreach ($logs as $offset => $log) {
            $baseTime = $timestamps['submitted_at'] ?? '2025-10-01 08:00:00';
            $createdAt = $log['created_at'] ?? date('Y-m-d H:i:s', strtotime($baseTime.' +'.($offset * 5).' minutes'));

            $submission->approvalLogs()->create([
                'acted_by' => $log['acted_by'],
                'action' => $log['action'],
                'remarks' => $log['remarks'],
                'metadata' => [
                    'module' => 'grading-workflow',
                    'grading_period' => $gradingPeriod->quarter->label(),
                    'teacher_load_id' => $teacherLoad->id,
                ],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        return $submission->fresh(['quarterlyGrades', 'approvalLogs']);
    }

    private function syncReportCardRecord(
        SectionRoster $roster,
        GradingPeriod $gradingPeriod,
        Template $template,
        TemplateDocumentType $documentType,
        array $attributes,
        array $auditLogs,
    ): void {
        $record = $roster->reportCardRecords()->updateOrCreate(
            [
                'grading_period_id' => $gradingPeriod->id,
                'document_type' => $documentType,
                'record_version' => $attributes['record_version'],
            ],
            [
                'section_id' => $roster->section_id,
                'school_year_id' => $roster->school_year_id,
                'learner_id' => $roster->learner_id,
                'template_id' => $template->id,
                'generated_by' => $attributes['generated_by'],
                'template_version' => $attributes['template_version'],
                'file_name' => $attributes['file_name'],
                'file_disk' => $attributes['file_disk'],
                'file_path' => $attributes['file_path'],
                'is_finalized' => $attributes['is_finalized'],
                'generated_at' => $attributes['generated_at'],
                'finalized_at' => $attributes['finalized_at'],
                'finalized_by' => $attributes['finalized_by'],
                'payload' => $attributes['payload'],
            ],
        );

        $this->storeSpreadsheet($attributes['file_path'], [
            ['Document type', $documentType->label()],
            ['Learner', $roster->learner->last_name.', '.$roster->learner->first_name],
            ['Section', $roster->section->name],
            ['School year', $roster->schoolYear->name],
            ['Grading period', $gradingPeriod->quarter->label()],
            ['Record version', $attributes['record_version']],
            ['Template version', $attributes['template_version']],
        ]);

        $record->auditLogs()->delete();

        foreach ($auditLogs as $auditLog) {
            $record->auditLogs()->create([
                'acted_by' => $auditLog['acted_by'],
                'action' => $auditLog['action'],
                'remarks' => $auditLog['remarks'],
                'metadata' => $auditLog['metadata'],
                'created_at' => $auditLog['created_at'],
                'updated_at' => $auditLog['created_at'],
            ]);
        }
    }

    private function storeSpreadsheet(string $path, array $rows): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex + 1, $rowIndex + 1, $value);
            }
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'uat-demo');
        $spreadsheetPath = $temporaryPath.'.xlsx';

        rename($temporaryPath, $spreadsheetPath);

        $writer = new Xlsx($spreadsheet);
        $writer->save($spreadsheetPath);

        Storage::disk('local')->put($path, file_get_contents($spreadsheetPath) ?: '');

        @unlink($spreadsheetPath);
    }
}
