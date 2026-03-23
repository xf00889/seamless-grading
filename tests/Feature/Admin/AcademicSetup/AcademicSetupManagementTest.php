<?php

namespace Tests\Feature\Admin\AcademicSetup;

use App\Enums\GradingQuarter;
use App\Enums\RoleName;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicSetupManagementTest extends TestCase
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

    public function test_admin_can_view_academic_setup_pages(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $schoolYear = SchoolYear::factory()->create();
        $gradingPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::First,
        ]);
        $gradeLevel = GradeLevel::factory()->create();
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $subject = Subject::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.academic-setup'))
            ->assertOk();

        $this->get(route('admin.academic-setup.school-years.index'))->assertOk();
        $this->get(route('admin.academic-setup.school-years.create'))->assertOk();
        $this->get(route('admin.academic-setup.school-years.show', $schoolYear))->assertOk();
        $this->get(route('admin.academic-setup.school-years.edit', $schoolYear))->assertOk();

        $this->get(route('admin.academic-setup.grading-periods.index'))->assertOk();
        $this->get(route('admin.academic-setup.grading-periods.create', ['school_year_id' => $schoolYear->id]))->assertOk();
        $this->get(route('admin.academic-setup.grading-periods.show', $gradingPeriod))->assertOk();
        $this->get(route('admin.academic-setup.grading-periods.edit', $gradingPeriod))->assertOk();

        $this->get(route('admin.academic-setup.grade-levels.index'))->assertOk();
        $this->get(route('admin.academic-setup.grade-levels.create'))->assertOk();
        $this->get(route('admin.academic-setup.grade-levels.show', $gradeLevel))->assertOk();
        $this->get(route('admin.academic-setup.grade-levels.edit', $gradeLevel))->assertOk();

        $this->get(route('admin.academic-setup.sections.index'))->assertOk();
        $this->get(route('admin.academic-setup.sections.create'))->assertOk();
        $this->get(route('admin.academic-setup.sections.show', $section))->assertOk();
        $this->get(route('admin.academic-setup.sections.edit', $section))->assertOk();

        $this->get(route('admin.academic-setup.subjects.index'))->assertOk();
        $this->get(route('admin.academic-setup.subjects.create'))->assertOk();
        $this->get(route('admin.academic-setup.subjects.show', $subject))->assertOk();
        $this->get(route('admin.academic-setup.subjects.edit', $subject))->assertOk();
    }

    public function test_non_admin_users_are_redirected_from_academic_setup_get_routes(): void
    {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);

        $routes = [
            route('admin.academic-setup'),
            route('admin.academic-setup.school-years.index'),
            route('admin.academic-setup.school-years.create'),
            route('admin.academic-setup.grading-periods.index'),
            route('admin.academic-setup.grade-levels.index'),
            route('admin.academic-setup.sections.index'),
            route('admin.academic-setup.subjects.index'),
        ];

        $this->actingAs($teacher);

        foreach ($routes as $route) {
            $this->get($route)->assertRedirect(route('access.denied'));
        }
    }

    public function test_non_admin_users_cannot_write_academic_setup_records(): void
    {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);

        $this->actingAs($teacher)
            ->post(route('admin.academic-setup.school-years.store'), [
                'name' => '2030-2031',
                'starts_on' => '2030-06-01',
                'ends_on' => '2031-05-31',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_manage_school_years(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $existingActiveYear = SchoolYear::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.academic-setup.school-years.store'), [
            'name' => '2031-2032',
            'starts_on' => '2031-06-01',
            'ends_on' => '2032-05-31',
        ]);

        $schoolYear = SchoolYear::query()->where('name', '2031-2032')->firstOrFail();

        $response->assertRedirect(route('admin.academic-setup.school-years.show', ['school_year' => $schoolYear]));
        $this->assertDatabaseHas('school_years', ['id' => $schoolYear->id]);

        $this->post(route('admin.academic-setup.school-years.activate', $schoolYear))
            ->assertRedirect(route('admin.academic-setup.school-years.show', ['school_year' => $schoolYear]));

        $this->assertDatabaseHas('school_years', ['id' => $schoolYear->id, 'is_active' => true]);
        $this->assertDatabaseHas('school_years', ['id' => $existingActiveYear->id, 'is_active' => false]);

        $this->put(route('admin.academic-setup.school-years.update', $schoolYear), [
            'name' => '2031-2032 Revised',
            'starts_on' => '2031-06-15',
            'ends_on' => '2032-05-15',
        ])->assertRedirect(route('admin.academic-setup.school-years.show', ['school_year' => $schoolYear]));

        $this->assertDatabaseHas('school_years', [
            'id' => $schoolYear->id,
            'name' => '2031-2032 Revised',
        ]);

        $this->post(route('admin.academic-setup.school-years.deactivate', $schoolYear))
            ->assertRedirect(route('admin.academic-setup.school-years.show', ['school_year' => $schoolYear]));

        $this->assertDatabaseHas('school_years', ['id' => $schoolYear->id, 'is_active' => false]);

        $this->delete(route('admin.academic-setup.school-years.destroy', $schoolYear))
            ->assertRedirect(route('admin.academic-setup.school-years.index'));

        $this->assertDatabaseMissing('school_years', ['id' => $schoolYear->id]);
    }

    public function test_school_year_validation_is_enforced(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);

        SchoolYear::factory()->create(['name' => '2032-2033']);

        $this->actingAs($admin)
            ->from(route('admin.academic-setup.school-years.create'))
            ->post(route('admin.academic-setup.school-years.store'), [
                'name' => '2032-2033',
                'starts_on' => '2032-06-01',
                'ends_on' => '2032-05-31',
            ])
            ->assertRedirect(route('admin.academic-setup.school-years.create'))
            ->assertSessionHasErrors(['name', 'ends_on']);
    }

    public function test_admin_can_manage_grading_periods_and_enforce_sequence_rules(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $schoolYear = SchoolYear::factory()->create([
            'starts_on' => '2033-06-01',
            'ends_on' => '2034-05-31',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.academic-setup.grading-periods.store'), [
                'school_year_id' => $schoolYear->id,
                'quarter' => GradingQuarter::First->value,
                'starts_on' => '2033-06-01',
                'ends_on' => '2033-08-31',
            ])
            ->assertRedirect();

        $this->from(route('admin.academic-setup.grading-periods.create'))
            ->post(route('admin.academic-setup.grading-periods.store'), [
                'school_year_id' => $schoolYear->id,
                'quarter' => GradingQuarter::Third->value,
                'starts_on' => '2033-09-01',
                'ends_on' => '2033-11-30',
            ])
            ->assertRedirect(route('admin.academic-setup.grading-periods.create'))
            ->assertSessionHasErrors('quarter');

        $this->from(route('admin.academic-setup.grading-periods.create'))
            ->post(route('admin.academic-setup.grading-periods.store'), [
                'school_year_id' => $schoolYear->id,
                'quarter' => GradingQuarter::Second->value,
                'starts_on' => '2033-05-15',
                'ends_on' => '2034-06-15',
            ])
            ->assertRedirect(route('admin.academic-setup.grading-periods.create'))
            ->assertSessionHasErrors(['starts_on', 'ends_on']);

        $this->post(route('admin.academic-setup.grading-periods.store'), [
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::Second->value,
            'starts_on' => '2033-09-01',
            'ends_on' => '2033-11-30',
        ])->assertRedirect();

        $gradingPeriod = GradingPeriod::query()
            ->where('school_year_id', $schoolYear->id)
            ->where('quarter', GradingQuarter::Second->value)
            ->firstOrFail();

        $this->put(route('admin.academic-setup.grading-periods.update', $gradingPeriod), [
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::Second->value,
            'starts_on' => '2033-09-05',
            'ends_on' => '2033-12-01',
        ])->assertRedirect(route('admin.academic-setup.grading-periods.show', ['grading_period' => $gradingPeriod]));

        $this->post(route('admin.academic-setup.grading-periods.open', $gradingPeriod))
            ->assertRedirect(route('admin.academic-setup.grading-periods.show', ['grading_period' => $gradingPeriod]));
        $this->assertDatabaseHas('grading_periods', ['id' => $gradingPeriod->id, 'is_open' => true]);

        $this->post(route('admin.academic-setup.grading-periods.close', $gradingPeriod))
            ->assertRedirect(route('admin.academic-setup.grading-periods.show', ['grading_period' => $gradingPeriod]));
        $this->assertDatabaseHas('grading_periods', ['id' => $gradingPeriod->id, 'is_open' => false]);

        $this->delete(route('admin.academic-setup.grading-periods.destroy', $gradingPeriod))
            ->assertRedirect(route('admin.academic-setup.grading-periods.index'));
        $this->assertDatabaseMissing('grading_periods', ['id' => $gradingPeriod->id]);
    }

    public function test_admin_can_manage_grade_levels_and_prevent_deleting_linked_records(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $schoolYear = SchoolYear::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.academic-setup.grade-levels.store'), [
                'code' => 'GRADE-31',
                'name' => 'Grade 31',
                'sort_order' => 31,
            ])
            ->assertRedirect();

        $gradeLevel = GradeLevel::query()->where('code', 'GRADE-31')->firstOrFail();

        $this->put(route('admin.academic-setup.grade-levels.update', $gradeLevel), [
            'code' => 'GRADE-31A',
            'name' => 'Grade 31 Advanced',
            'sort_order' => 32,
        ])->assertRedirect(route('admin.academic-setup.grade-levels.show', ['grade_level' => $gradeLevel]));

        Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
        ]);

        $this->from(route('admin.academic-setup.grade-levels.show', $gradeLevel))
            ->delete(route('admin.academic-setup.grade-levels.destroy', $gradeLevel))
            ->assertRedirect(route('admin.academic-setup.grade-levels.show', ['grade_level' => $gradeLevel]))
            ->assertSessionHasErrors('record');

        $deletableLevel = GradeLevel::factory()->create();

        $this->delete(route('admin.academic-setup.grade-levels.destroy', $deletableLevel))
            ->assertRedirect(route('admin.academic-setup.grade-levels.index'));

        $this->assertDatabaseMissing('grade_levels', ['id' => $deletableLevel->id]);
    }

    public function test_admin_can_manage_sections_and_validate_adviser_relationships(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $schoolYear = SchoolYear::factory()->create();
        $gradeLevel = GradeLevel::factory()->create();
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);

        $this->actingAs($admin)
            ->from(route('admin.academic-setup.sections.create'))
            ->post(route('admin.academic-setup.sections.store'), [
                'school_year_id' => $schoolYear->id,
                'grade_level_id' => $gradeLevel->id,
                'adviser_id' => $teacher->id,
                'name' => 'Section Mercury',
            ])
            ->assertRedirect(route('admin.academic-setup.sections.create'))
            ->assertSessionHasErrors('adviser_id');

        $this->post(route('admin.academic-setup.sections.store'), [
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
            'name' => 'Section Mercury',
        ])->assertRedirect();

        $section = Section::query()->where('name', 'Section Mercury')->firstOrFail();

        $this->put(route('admin.academic-setup.sections.update', $section), [
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => null,
            'name' => 'Section Neptune',
        ])->assertRedirect(route('admin.academic-setup.sections.show', ['section' => $section]));

        $this->post(route('admin.academic-setup.sections.deactivate', $section))
            ->assertRedirect(route('admin.academic-setup.sections.show', ['section' => $section]));
        $this->assertDatabaseHas('sections', ['id' => $section->id, 'is_active' => false]);

        $this->post(route('admin.academic-setup.sections.activate', $section))
            ->assertRedirect(route('admin.academic-setup.sections.show', ['section' => $section]));
        $this->assertDatabaseHas('sections', ['id' => $section->id, 'is_active' => true]);

        $this->delete(route('admin.academic-setup.sections.destroy', $section))
            ->assertRedirect(route('admin.academic-setup.sections.index'));
        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
    }

    public function test_admin_can_manage_subjects(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);

        $this->actingAs($admin)
            ->post(route('admin.academic-setup.subjects.store'), [
                'code' => 'SCI-201',
                'name' => 'Applied Science',
                'short_name' => 'SCI',
            ])
            ->assertRedirect();

        $subject = Subject::query()->where('code', 'SCI-201')->firstOrFail();
        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'is_core' => false,
        ]);

        $this->put(route('admin.academic-setup.subjects.update', $subject), [
            'code' => 'SCI-202',
            'name' => 'Advanced Science',
            'short_name' => 'ASCI',
            'is_core' => '1',
        ])->assertRedirect(route('admin.academic-setup.subjects.show', ['subject' => $subject]));

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'code' => 'SCI-202',
            'is_core' => true,
        ]);

        $this->post(route('admin.academic-setup.subjects.deactivate', $subject))
            ->assertRedirect(route('admin.academic-setup.subjects.show', ['subject' => $subject]));
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'is_active' => false]);

        $this->post(route('admin.academic-setup.subjects.activate', $subject))
            ->assertRedirect(route('admin.academic-setup.subjects.show', ['subject' => $subject]));
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'is_active' => true]);

        $this->delete(route('admin.academic-setup.subjects.destroy', $subject))
            ->assertRedirect(route('admin.academic-setup.subjects.index'));
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
