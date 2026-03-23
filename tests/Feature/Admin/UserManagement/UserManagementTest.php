<?php

namespace Tests\Feature\Admin\UserManagement;

use App\Enums\RoleName;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherLoad;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_admin_can_view_user_management_pages(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $schoolYear = SchoolYear::factory()->create();
        $gradeLevel = GradeLevel::factory()->create();
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $subject = Subject::factory()->create();
        $teacherLoad = TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.user-management'))
            ->assertOk();

        $this->get(route('admin.user-management.users.index'))->assertOk();
        $this->get(route('admin.user-management.users.create'))->assertOk();
        $this->get(route('admin.user-management.users.show', $teacher))->assertOk();
        $this->get(route('admin.user-management.users.edit', $teacher))->assertOk();

        $this->get(route('admin.user-management.teacher-loads.index'))->assertOk();
        $this->get(route('admin.user-management.teacher-loads.create'))->assertOk();
        $this->get(route('admin.user-management.teacher-loads.show', $teacherLoad))->assertOk();
        $this->get(route('admin.user-management.teacher-loads.edit', $teacherLoad))->assertOk();
    }

    public function test_non_admin_users_are_redirected_from_user_management_get_routes(): void
    {
        $routes = [
            route('admin.user-management'),
            route('admin.user-management.users.index'),
            route('admin.user-management.users.create'),
            route('admin.user-management.teacher-loads.index'),
            route('admin.user-management.teacher-loads.create'),
        ];

        foreach ([RoleName::Teacher, RoleName::Adviser, RoleName::Registrar] as $role) {
            $this->actingAs($this->createUserWithRole($role->value));

            foreach ($routes as $route) {
                $this->get($route)->assertRedirect(route('access.denied'));
            }
        }
    }

    public function test_non_admin_users_cannot_write_user_management_records(): void
    {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $schoolYear = SchoolYear::factory()->create();
        $gradeLevel = GradeLevel::factory()->create();
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
        ]);
        $subject = Subject::factory()->create();

        $this->actingAs($teacher)
            ->post(route('admin.user-management.users.store'), [
                'name' => 'Restricted User',
                'email' => 'restricted@example.com',
                'role' => RoleName::Teacher->value,
                'password' => 'Passw0rd!',
                'password_confirmation' => 'Passw0rd!',
            ])
            ->assertForbidden();

        $this->post(route('admin.user-management.teacher-loads.store'), [
            'teacher_id' => $teacher->id,
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ])->assertForbidden();
    }

    public function test_admin_can_manage_users_with_roles_and_status(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);

        $response = $this->actingAs($admin)->post(route('admin.user-management.users.store'), [
            'name' => 'Faculty User',
            'email' => 'faculty@example.com',
            'role' => RoleName::Teacher->value,
            'password' => 'Passw0rd!',
            'password_confirmation' => 'Passw0rd!',
        ]);

        $managedUser = User::query()->where('email', 'faculty@example.com')->firstOrFail();

        $response->assertRedirect(route('admin.user-management.users.show', $managedUser));
        $this->assertTrue($managedUser->hasRole(RoleName::Teacher->value));
        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'is_active' => true,
        ]);

        $this->put(route('admin.user-management.users.update', $managedUser), [
            'name' => 'Faculty User Updated',
            'email' => 'faculty.updated@example.com',
            'role' => RoleName::Adviser->value,
        ])->assertRedirect(route('admin.user-management.users.show', $managedUser));

        $managedUser->refresh();
        $this->assertTrue($managedUser->hasRole(RoleName::Adviser->value));
        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'Faculty User Updated',
            'email' => 'faculty.updated@example.com',
        ]);

        $this->post(route('admin.user-management.users.deactivate', $managedUser))
            ->assertRedirect(route('admin.user-management.users.show', $managedUser));
        $this->assertDatabaseHas('users', ['id' => $managedUser->id, 'is_active' => false]);

        $this->post(route('admin.user-management.users.activate', $managedUser))
            ->assertRedirect(route('admin.user-management.users.show', $managedUser));
        $this->assertDatabaseHas('users', ['id' => $managedUser->id, 'is_active' => true]);

        $this->delete(route('admin.user-management.users.destroy', $managedUser))
            ->assertRedirect(route('admin.user-management.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $managedUser->id]);
    }

    public function test_user_validation_and_assignment_safety_rules_are_enforced(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $existing = User::factory()->create(['email' => 'existing@example.com']);
        $existing->assignRole(RoleName::Teacher->value);

        $this->actingAs($admin)
            ->from(route('admin.user-management.users.create'))
            ->post(route('admin.user-management.users.store'), [
                'name' => 'Duplicate Email',
                'email' => 'existing@example.com',
                'role' => RoleName::Teacher->value,
                'password' => 'Passw0rd!',
                'password_confirmation' => 'Passw0rd!',
            ])
            ->assertRedirect(route('admin.user-management.users.create'))
            ->assertSessionHasErrors('email');

        $this->from(route('admin.user-management.users.show', $admin))
            ->post(route('admin.user-management.users.deactivate', $admin))
            ->assertRedirect(route('admin.user-management.users.show', $admin))
            ->assertSessionHasErrors('record');

        $schoolYear = SchoolYear::factory()->create();
        $gradeLevel = GradeLevel::factory()->create();
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $subject = Subject::factory()->create();

        TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);

        $this->from(route('admin.user-management.users.show', $teacher))
            ->delete(route('admin.user-management.users.destroy', $teacher))
            ->assertRedirect(route('admin.user-management.users.show', $teacher))
            ->assertSessionHasErrors('record');

        $this->from(route('admin.user-management.users.show', $adviser))
            ->delete(route('admin.user-management.users.destroy', $adviser))
            ->assertRedirect(route('admin.user-management.users.show', $adviser))
            ->assertSessionHasErrors('record');
    }

    public function test_admin_can_manage_teacher_loads_and_filter_results(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $teacherA = $this->createUserWithRole(RoleName::Teacher->value, [
            'name' => 'Teacher Alpha',
            'email' => 'teacher.alpha@example.com',
        ]);
        $teacherB = $this->createUserWithRole(RoleName::Teacher->value, [
            'name' => 'Teacher Beta',
            'email' => 'teacher.beta@example.com',
        ]);
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 7']);
        $schoolYearA = SchoolYear::factory()->create(['name' => '2037-2038']);
        $schoolYearB = SchoolYear::factory()->create(['name' => '2038-2039']);
        $sectionA = Section::factory()->create([
            'name' => 'Section Alpha',
            'school_year_id' => $schoolYearA->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $sectionB = Section::factory()->create([
            'name' => 'Section Beta',
            'school_year_id' => $schoolYearB->id,
            'grade_level_id' => $gradeLevel->id,
        ]);
        $subjectA = Subject::factory()->create(['code' => 'MATH-A', 'name' => 'Mathematics Alpha']);
        $subjectB = Subject::factory()->create(['code' => 'SCI-B', 'name' => 'Science Beta']);
        $subjectC = Subject::factory()->create(['code' => 'ENG-C', 'name' => 'English Gamma']);

        $teacherLoad = TeacherLoad::factory()->create([
            'teacher_id' => $teacherA->id,
            'school_year_id' => $schoolYearA->id,
            'section_id' => $sectionA->id,
            'subject_id' => $subjectA->id,
        ]);

        TeacherLoad::factory()->create([
            'teacher_id' => $teacherB->id,
            'school_year_id' => $schoolYearB->id,
            'section_id' => $sectionB->id,
            'subject_id' => $subjectB->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.user-management.teacher-loads.index', [
                'search' => 'Alpha',
                'school_year_id' => $schoolYearA->id,
                'teacher_id' => $teacherA->id,
                'section_id' => $sectionA->id,
                'subject_id' => $subjectA->id,
            ]))
            ->assertOk()
            ->assertSeeText('Teacher Alpha')
            ->assertSeeText('Section Alpha')
            ->assertSeeText('teacher.alpha@example.com')
            ->assertDontSeeText('teacher.beta@example.com');

        $this->post(route('admin.user-management.teacher-loads.store'), [
            'teacher_id' => $teacherB->id,
            'school_year_id' => $schoolYearA->id,
            'section_id' => $sectionA->id,
            'subject_id' => $subjectB->id,
        ])->assertRedirect();

        $newTeacherLoad = TeacherLoad::query()
            ->where('teacher_id', $teacherB->id)
            ->where('school_year_id', $schoolYearA->id)
            ->where('section_id', $sectionA->id)
            ->where('subject_id', $subjectB->id)
            ->firstOrFail();

        $this->put(route('admin.user-management.teacher-loads.update', $newTeacherLoad), [
            'teacher_id' => $teacherB->id,
            'school_year_id' => $schoolYearA->id,
            'section_id' => $sectionA->id,
            'subject_id' => $subjectC->id,
        ])->assertRedirect(route('admin.user-management.teacher-loads.show', $newTeacherLoad));

        $this->assertDatabaseHas('teacher_loads', [
            'id' => $newTeacherLoad->id,
            'subject_id' => $subjectC->id,
        ]);

        $this->post(route('admin.user-management.teacher-loads.deactivate', $teacherLoad))
            ->assertRedirect(route('admin.user-management.teacher-loads.show', $teacherLoad));
        $this->assertDatabaseHas('teacher_loads', ['id' => $teacherLoad->id, 'is_active' => false]);

        $this->post(route('admin.user-management.teacher-loads.activate', $teacherLoad))
            ->assertRedirect(route('admin.user-management.teacher-loads.show', $teacherLoad));
        $this->assertDatabaseHas('teacher_loads', ['id' => $teacherLoad->id, 'is_active' => true]);

        $this->delete(route('admin.user-management.teacher-loads.destroy', $newTeacherLoad))
            ->assertRedirect(route('admin.user-management.teacher-loads.index'));
        $this->assertDatabaseMissing('teacher_loads', ['id' => $newTeacherLoad->id]);
    }

    public function test_teacher_load_validation_and_workflow_safety_rules_are_enforced(): void
    {
        $admin = $this->createUserWithRole(RoleName::Admin->value);
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $inactiveTeacher = $this->createUserWithRole(RoleName::Teacher->value, [
            'is_active' => false,
        ]);
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $schoolYearA = SchoolYear::factory()->create();
        $schoolYearB = SchoolYear::factory()->create();
        $gradeLevel = GradeLevel::factory()->create();
        $sectionA = Section::factory()->create([
            'school_year_id' => $schoolYearA->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $sectionB = Section::factory()->create([
            'school_year_id' => $schoolYearB->id,
            'grade_level_id' => $gradeLevel->id,
        ]);
        $subjectA = Subject::factory()->create();
        $subjectB = Subject::factory()->create();

        $teacherLoad = TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $schoolYearA->id,
            'section_id' => $sectionA->id,
            'subject_id' => $subjectA->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.user-management.teacher-loads.create'))
            ->post(route('admin.user-management.teacher-loads.store'), [
                'teacher_id' => $teacher->id,
                'school_year_id' => $schoolYearA->id,
                'section_id' => $sectionA->id,
                'subject_id' => $subjectA->id,
            ])
            ->assertRedirect(route('admin.user-management.teacher-loads.create'))
            ->assertSessionHasErrors('subject_id');

        $this->from(route('admin.user-management.teacher-loads.create'))
            ->post(route('admin.user-management.teacher-loads.store'), [
                'teacher_id' => $adviser->id,
                'school_year_id' => $schoolYearA->id,
                'section_id' => $sectionA->id,
                'subject_id' => $subjectB->id,
            ])
            ->assertRedirect(route('admin.user-management.teacher-loads.create'))
            ->assertSessionHasErrors('teacher_id');

        $this->from(route('admin.user-management.teacher-loads.create'))
            ->post(route('admin.user-management.teacher-loads.store'), [
                'teacher_id' => $inactiveTeacher->id,
                'school_year_id' => $schoolYearA->id,
                'section_id' => $sectionA->id,
                'subject_id' => $subjectB->id,
            ])
            ->assertRedirect(route('admin.user-management.teacher-loads.create'))
            ->assertSessionHasErrors('teacher_id');

        $this->from(route('admin.user-management.teacher-loads.create'))
            ->post(route('admin.user-management.teacher-loads.store'), [
                'teacher_id' => $teacher->id,
                'school_year_id' => $schoolYearA->id,
                'section_id' => $sectionB->id,
                'subject_id' => $subjectB->id,
            ])
            ->assertRedirect(route('admin.user-management.teacher-loads.create'))
            ->assertSessionHasErrors('section_id');

        GradeSubmission::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
        ]);

        $this->from(route('admin.user-management.teacher-loads.show', $teacherLoad))
            ->put(route('admin.user-management.teacher-loads.update', $teacherLoad), [
                'teacher_id' => $teacher->id,
                'school_year_id' => $schoolYearA->id,
                'section_id' => $sectionA->id,
                'subject_id' => $subjectB->id,
            ])
            ->assertRedirect(route('admin.user-management.teacher-loads.show', $teacherLoad))
            ->assertSessionHasErrors('record');

        $this->from(route('admin.user-management.teacher-loads.show', $teacherLoad))
            ->delete(route('admin.user-management.teacher-loads.destroy', $teacherLoad))
            ->assertRedirect(route('admin.user-management.teacher-loads.show', $teacherLoad))
            ->assertSessionHasErrors('record');
    }

    private function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
