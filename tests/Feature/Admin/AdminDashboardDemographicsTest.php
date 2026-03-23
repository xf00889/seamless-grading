<?php

namespace Tests\Feature\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\GradingQuarter;
use App\Enums\LearnerSex;
use App\Enums\RoleName;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardDemographicsTest extends TestCase
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

    public function test_admin_dashboard_renders_admin_demographics_for_the_active_official_roster(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $adviser = User::factory()->create();
        $gradeSeven = GradeLevel::factory()->create([
            'code' => 'GRADE-7',
            'name' => 'Grade 7',
            'sort_order' => 7,
        ]);
        $gradeEight = GradeLevel::factory()->create([
            'code' => 'GRADE-8',
            'name' => 'Grade 8',
            'sort_order' => 8,
        ]);

        $activeSchoolYear = SchoolYear::factory()->create([
            'name' => '2025-2026',
            'starts_on' => '2025-06-01',
            'ends_on' => '2026-05-31',
            'is_active' => true,
        ]);

        GradingPeriod::factory()->create([
            'school_year_id' => $activeSchoolYear->id,
            'quarter' => GradingQuarter::Second,
            'starts_on' => '2025-09-01',
            'ends_on' => '2025-10-31',
            'is_open' => true,
        ]);

        $activeSection = Section::factory()->create([
            'school_year_id' => $activeSchoolYear->id,
            'grade_level_id' => $gradeSeven->id,
            'adviser_id' => $adviser->id,
            'name' => 'Narra',
        ]);
        $gradeEightSection = Section::factory()->create([
            'school_year_id' => $activeSchoolYear->id,
            'grade_level_id' => $gradeEight->id,
            'adviser_id' => $adviser->id,
            'name' => 'Molave',
        ]);

        $this->createOfficialRoster($activeSchoolYear, $activeSection, LearnerSex::Male, EnrollmentStatus::Active, '2016-05-31');
        $this->createOfficialRoster($activeSchoolYear, $activeSection, LearnerSex::Female, EnrollmentStatus::Inactive, '2014-05-31');
        $this->createOfficialRoster($activeSchoolYear, $activeSection, LearnerSex::Female, EnrollmentStatus::Active, '2011-05-31');
        $this->createOfficialRoster($activeSchoolYear, $gradeEightSection, LearnerSex::Male, EnrollmentStatus::TransferredOut, '2008-05-31');
        $this->createOfficialRoster($activeSchoolYear, $gradeEightSection, LearnerSex::Female, EnrollmentStatus::Dropped, null);

        $previousSchoolYear = SchoolYear::factory()->create([
            'name' => '2024-2025',
            'starts_on' => '2024-06-01',
            'ends_on' => '2025-05-31',
            'is_active' => false,
        ]);

        $previousSection = Section::factory()->create([
            'school_year_id' => $previousSchoolYear->id,
            'grade_level_id' => $gradeSeven->id,
            'adviser_id' => $adviser->id,
            'name' => 'Acacia',
        ]);

        $this->createOfficialRoster($previousSchoolYear, $previousSection, LearnerSex::Male, EnrollmentStatus::Dropped, '2013-05-31');

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeText('Sex Distribution')
            ->assertSeeText('Age Bands')
            ->assertSeeText('Enrollment Status')
            ->assertSeeText('Learners By Grade Level')
            ->assertSeeText('5 official')
            ->assertSeeText('As of May 31, 2026')
            ->assertSee('Official roster sex distribution', false)
            ->assertSee('Official roster age-band distribution', false)
            ->assertSee('Official roster enrollment status distribution', false)
            ->assertSee('Official roster grade-level distribution', false)
            ->assertSeeText('Male: 2')
            ->assertSeeText('Female: 3')
            ->assertSeeText('10 and below: 1')
            ->assertSeeText('11-13: 1')
            ->assertSeeText('14-16: 1')
            ->assertSeeText('17+: 1')
            ->assertSeeText('Unknown: 1')
            ->assertSeeText('Active: 2')
            ->assertSeeText('Inactive: 1')
            ->assertSeeText('Transferred out: 1')
            ->assertSeeText('Dropped: 1')
            ->assertSeeText('Grade 7: 3')
            ->assertSeeText('Grade 8: 2');
    }

    private function createOfficialRoster(
        SchoolYear $schoolYear,
        Section $section,
        LearnerSex $sex,
        EnrollmentStatus $enrollmentStatus,
        ?string $birthDate = null,
    ): void {
        $learner = Learner::factory()->create([
            'sex' => $sex,
            'birth_date' => $birthDate,
            'enrollment_status' => $enrollmentStatus,
        ]);

        SectionRoster::factory()->create([
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'learner_id' => $learner->id,
            'import_batch_id' => null,
            'enrollment_status' => $enrollmentStatus,
            'is_official' => true,
        ]);
    }
}
