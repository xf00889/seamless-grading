<?php

namespace Tests\Feature\Registrar;

use App\Enums\GradingQuarter;
use App\Enums\RoleName;
use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
use App\Models\Learner;
use App\Models\ReportCardRecord;
use App\Models\ReportCardRecordAuditLog;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrarRecordsRepositoryTest extends TestCase
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

    public function test_registrar_only_access_is_enforced_for_repository_history_and_verification_routes(): void
    {
        $registrar = $this->createUserWithRole(RoleName::Registrar->value);
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $record = $this->createFinalizedRecord(
            learnerName: 'Alicia Registrar',
            lrn: '500000000001',
            documentType: TemplateDocumentType::Sf9,
        );

        $this->actingAs($registrar)
            ->get(route('registrar.records.index'))
            ->assertOk();

        $this->actingAs($registrar)
            ->get(route('registrar.records.learners.show', ['learner' => $record->learner_id]))
            ->assertOk();

        $this->actingAs($registrar)
            ->get(route('registrar.records.show', ['report_card_record' => $record]))
            ->assertOk();

        $this->actingAs($teacher)
            ->get(route('registrar.records.index'))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($teacher)
            ->get(route('registrar.records.learners.show', ['learner' => $record->learner_id]))
            ->assertRedirect(route('access.denied'));

        $this->actingAs($teacher)
            ->get(route('registrar.records.show', ['report_card_record' => $record]))
            ->assertRedirect(route('access.denied'));
    }

    public function test_repository_filters_and_search_show_only_finalized_official_sf9_and_sf10_records(): void
    {
        $registrar = $this->createUserWithRole(RoleName::Registrar->value);
        $schoolYearA = SchoolYear::factory()->create(['name' => '2050-2051']);
        $schoolYearB = SchoolYear::factory()->create(['name' => '2051-2052']);
        $gradeLevel6 = GradeLevel::factory()->create(['name' => 'Grade 6', 'sort_order' => 6]);
        $gradeLevel7 = GradeLevel::factory()->create(['name' => 'Grade 7', 'sort_order' => 7]);

        $sf9Record = $this->createFinalizedRecord(
            learnerName: 'Alicia Filter',
            lrn: '500000000011',
            documentType: TemplateDocumentType::Sf9,
            schoolYear: $schoolYearA,
            gradeLevel: $gradeLevel6,
            sectionName: 'Section Mabini',
        );

        $sf10Record = $this->createFinalizedRecord(
            learnerName: 'Bruno Filter',
            lrn: '500000000012',
            documentType: TemplateDocumentType::Sf10,
            schoolYear: $schoolYearB,
            gradeLevel: $gradeLevel7,
            sectionName: 'Section Rizal',
        );

        $unfinalizedRecord = $this->createFinalizedRecord(
            learnerName: 'Hidden Draft',
            lrn: '500000000013',
            documentType: TemplateDocumentType::Sf9,
            finalized: false,
        );

        $unofficialRecord = $this->createFinalizedRecord(
            learnerName: 'Hidden Unofficial',
            lrn: '500000000014',
            documentType: TemplateDocumentType::Sf10,
            official: false,
        );

        $this->actingAs($registrar)
            ->get(route('registrar.records.index'))
            ->assertOk()
            ->assertSeeText('Alicia Filter')
            ->assertSeeText('Bruno Filter')
            ->assertDontSeeText('Hidden Draft')
            ->assertDontSeeText('Hidden Unofficial');

        $this->actingAs($registrar)
            ->get(route('registrar.records.index', [
                'search' => 'Alicia',
                'lrn' => '500000000011',
                'school_year_id' => $schoolYearA->id,
                'grade_level_id' => $gradeLevel6->id,
                'section_id' => $sf9Record->section_id,
                'document_type' => TemplateDocumentType::Sf9->value,
                'finalization_status' => 'finalized',
            ]))
            ->assertOk()
            ->assertSeeText('Alicia Filter')
            ->assertDontSeeText('Bruno Filter');

        $this->assertFalse($unfinalizedRecord->is_finalized);
        $this->assertFalse($unofficialRecord->sectionRoster()->value('is_official'));
        $this->assertTrue($sf10Record->is_finalized);
    }

    public function test_learner_history_page_shows_finalized_sf9_and_sf10_history_for_the_target_learner_only(): void
    {
        $registrar = $this->createUserWithRole(RoleName::Registrar->value);
        $learner = Learner::factory()->create([
            'first_name' => 'Carla',
            'last_name' => 'History',
            'lrn' => '500000000021',
        ]);

        $sf9Record = $this->createFinalizedRecord(
            learner: $learner,
            documentType: TemplateDocumentType::Sf9,
            sectionName: 'Section Narra',
        );

        $sf10Record = $this->createFinalizedRecord(
            learner: $learner,
            documentType: TemplateDocumentType::Sf10,
            sectionName: 'Section Narra',
        );

        $this->createFinalizedRecord(
            learnerName: 'Other Learner',
            lrn: '500000000022',
            documentType: TemplateDocumentType::Sf9,
        );

        $this->actingAs($registrar)
            ->get(route('registrar.records.learners.show', ['learner' => $learner]))
            ->assertOk()
            ->assertSeeText('Carla')
            ->assertSeeText('SF9')
            ->assertSeeText('SF10')
            ->assertSeeText('Version '.$sf9Record->record_version)
            ->assertSeeText('Version '.$sf10Record->record_version)
            ->assertDontSeeText('Other Learner');
    }

    public function test_verification_view_shows_metadata_and_audit_history_only_for_visible_finalized_records(): void
    {
        $registrar = $this->createUserWithRole(RoleName::Registrar->value);
        $record = $this->createFinalizedRecord(
            learnerName: 'Diana Verify',
            lrn: '500000000031',
            documentType: TemplateDocumentType::Sf10,
            recordVersion: 3,
            templateVersion: 5,
            payload: [
                'general_average' => '92.50',
                'year_end_status' => ['label' => 'Promoted'],
                'subject_rows' => [
                    [
                        'subject_name' => 'Mathematics',
                        'final_rating' => '93.00',
                        'remarks' => 'Passed',
                        'action_taken' => 'Promoted',
                    ],
                ],
            ],
        );

        ReportCardRecordAuditLog::factory()->create([
            'report_card_record_id' => $record->id,
            'acted_by' => $record->generated_by,
            'action' => 'exported',
            'remarks' => 'Generated initial final record.',
        ]);

        ReportCardRecordAuditLog::factory()->create([
            'report_card_record_id' => $record->id,
            'acted_by' => $record->finalized_by,
            'action' => 'finalized',
            'remarks' => 'Marked as finalized official record.',
        ]);

        $previousVersion = $this->createFinalizedRecord(
            learner: $record->learner,
            schoolYear: $record->schoolYear,
            gradeLevel: $record->section->gradeLevel,
            sectionName: $record->section->name,
            documentType: TemplateDocumentType::Sf10,
            recordVersion: 2,
            templateVersion: 4,
        );

        $this->actingAs($registrar)
            ->get(route('registrar.records.show', ['report_card_record' => $record]))
            ->assertOk()
            ->assertSeeText('Diana Verify')
            ->assertSeeText('SF10')
            ->assertSeeText('Version 3')
            ->assertSeeText('Template')
            ->assertSeeText('Promoted')
            ->assertSeeText('92.50')
            ->assertSeeText('Mathematics')
            ->assertSeeText('Generated initial final record.')
            ->assertSeeText('Marked as finalized official record.')
            ->assertSeeText('Version 2');

        $unfinalized = $this->createFinalizedRecord(
            learnerName: 'Hidden Verify',
            lrn: '500000000032',
            documentType: TemplateDocumentType::Sf9,
            finalized: false,
        );

        $unofficial = $this->createFinalizedRecord(
            learnerName: 'Hidden Officiality',
            lrn: '500000000033',
            documentType: TemplateDocumentType::Sf9,
            official: false,
        );

        $this->actingAs($registrar)
            ->get(route('registrar.records.show', ['report_card_record' => $unfinalized]))
            ->assertNotFound();

        $this->actingAs($registrar)
            ->get(route('registrar.records.show', ['report_card_record' => $unofficial]))
            ->assertNotFound();

        $this->assertSame(2, $previousVersion->record_version);
    }

    private function createFinalizedRecord(
        ?Learner $learner = null,
        ?SchoolYear $schoolYear = null,
        ?GradeLevel $gradeLevel = null,
        string $sectionName = 'Section Sampaguita',
        ?string $learnerName = null,
        ?string $lrn = null,
        TemplateDocumentType $documentType = TemplateDocumentType::Sf9,
        bool $finalized = true,
        bool $official = true,
        int $recordVersion = 1,
        int $templateVersion = 1,
        ?array $payload = null,
    ): ReportCardRecord {
        $schoolYear ??= SchoolYear::factory()->create();
        $gradeLevel ??= GradeLevel::factory()->create();

        $generatedBy = $this->createUserWithRole(RoleName::Adviser->value, ['name' => 'Adviser Generator']);
        $finalizedBy = $this->createUserWithRole(RoleName::Adviser->value, ['name' => 'Adviser Finalizer']);

        $learner ??= Learner::factory()->create([
            'first_name' => $learnerName ?? 'Learner',
            'last_name' => 'Record',
            'lrn' => $lrn ?? fake()->numerify('5000000000##'),
        ]);

        $section = Section::query()->firstOrCreate(
            [
                'school_year_id' => $schoolYear->id,
                'name' => $sectionName,
            ],
            [
                'grade_level_id' => $gradeLevel->id,
                'adviser_id' => $generatedBy->id,
                'is_active' => true,
            ],
        );

        $sectionRoster = SectionRoster::query()->firstOrCreate(
            [
                'school_year_id' => $schoolYear->id,
                'learner_id' => $learner->id,
            ],
            [
                'section_id' => $section->id,
                'is_official' => $official,
            ],
        );

        $sectionRoster->forceFill([
            'section_id' => $section->id,
            'is_official' => $official,
        ])->save();

        $gradingPeriod = GradingPeriod::query()->firstOrCreate(
            [
                'school_year_id' => $schoolYear->id,
                'quarter' => $documentType === TemplateDocumentType::Sf10
                    ? GradingQuarter::Fourth
                    : GradingQuarter::First,
            ],
            [
                'starts_on' => null,
                'ends_on' => null,
                'is_open' => false,
            ],
        );

        $template = Template::factory()->create([
            'document_type' => $documentType,
            'grade_level_id' => $documentType === TemplateDocumentType::Sf10 ? $gradeLevel->id : null,
            'version' => $templateVersion,
        ]);

        return ReportCardRecord::factory()->create([
            'section_roster_id' => $sectionRoster->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'learner_id' => $learner->id,
            'grading_period_id' => $gradingPeriod->id,
            'template_id' => $template->id,
            'document_type' => $documentType,
            'generated_by' => $generatedBy->id,
            'record_version' => $recordVersion,
            'template_version' => $templateVersion,
            'is_finalized' => $finalized,
            'finalized_at' => $finalized ? now()->subMinutes($recordVersion) : null,
            'finalized_by' => $finalized ? $finalizedBy->id : null,
            'payload' => $payload ?? [
                'general_average' => '90.00',
                'subject_rows' => [
                    [
                        'subject_name' => 'Mathematics',
                        'grade' => '90.00',
                        'remarks' => 'Passed',
                    ],
                ],
            ],
            'generated_at' => now()->subHours(2),
        ]);
    }

    private function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
