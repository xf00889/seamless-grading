<?php

namespace Tests\Feature\Teacher;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Enums\LearnerSex;
use App\Enums\RoleName;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\Learner;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\Subject;
use App\Models\TeacherLoad;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherWorkAreaTest extends TestCase
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

    public function test_teacher_dashboard_and_loads_page_only_show_owned_records(): void
    {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value, [
            'name' => 'Teacher One',
        ]);
        $otherTeacher = $this->createUserWithRole(RoleName::Teacher->value, [
            'name' => 'Teacher Two',
        ]);
        $adviser = $this->createUserWithRole(RoleName::Adviser->value, [
            'name' => 'Adviser One',
        ]);

        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 7']);
        $currentSchoolYear = SchoolYear::factory()->create(['name' => '2038-2039']);
        $pastSchoolYear = SchoolYear::factory()->create(['name' => '2037-2038']);
        $currentSection = Section::factory()->create([
            'name' => 'Section Narra',
            'school_year_id' => $currentSchoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $pastSection = Section::factory()->create([
            'name' => 'Section Acacia',
            'school_year_id' => $pastSchoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $otherSection = Section::factory()->create([
            'name' => 'Section Bamboo',
            'school_year_id' => $currentSchoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);

        $math = Subject::factory()->create(['name' => 'Mathematics', 'code' => 'MATH-7']);
        $science = Subject::factory()->create(['name' => 'Science', 'code' => 'SCI-7']);
        $english = Subject::factory()->create(['name' => 'English', 'code' => 'ENG-7']);

        $currentLoad = TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $currentSchoolYear->id,
            'section_id' => $currentSection->id,
            'subject_id' => $math->id,
            'is_active' => true,
        ]);
        $pastLoad = TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $pastSchoolYear->id,
            'section_id' => $pastSection->id,
            'subject_id' => $science->id,
            'is_active' => false,
        ]);
        $otherLoad = TeacherLoad::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'school_year_id' => $currentSchoolYear->id,
            'section_id' => $otherSection->id,
            'subject_id' => $english->id,
            'is_active' => true,
        ]);

        $currentPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $currentSchoolYear->id,
            'quarter' => GradingQuarter::First,
        ]);

        GradeSubmission::factory()->create([
            'teacher_load_id' => $currentLoad->id,
            'grading_period_id' => $currentPeriod->id,
            'status' => GradeSubmissionStatus::Returned,
            'adviser_remarks' => 'Please correct the written works column.',
            'returned_at' => now()->subDay(),
        ]);

        GradeSubmission::factory()->create([
            'teacher_load_id' => $otherLoad->id,
            'grading_period_id' => $currentPeriod->id,
            'status' => GradeSubmissionStatus::Returned,
            'adviser_remarks' => 'Other teacher returned submission.',
            'returned_at' => now()->subHours(3),
        ]);

        SectionRoster::factory()->create([
            'section_id' => $currentSection->id,
            'school_year_id' => $currentSchoolYear->id,
            'import_batch_id' => null,
        ]);
        SectionRoster::factory()->create([
            'section_id' => $currentSection->id,
            'school_year_id' => $currentSchoolYear->id,
            'import_batch_id' => null,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk()
            ->assertSeeText('Teacher Dashboard')
            ->assertSeeText('My teaching loads')
            ->assertSeeText('Please correct the written works column.')
            ->assertDontSeeText('Other teacher returned submission.');

        $this->get(route('teacher.loads.index'))
            ->assertOk()
            ->assertSeeText('Mathematics')
            ->assertSeeText('Science')
            ->assertSeeText('Section Narra')
            ->assertSeeText('Section Acacia')
            ->assertDontSeeText('English')
            ->assertDontSeeText('Section Bamboo');

        $this->get(route('teacher.loads.index', [
            'school_year_id' => $currentSchoolYear->id,
            'status' => 'active',
        ]))
            ->assertOk()
            ->assertSeeText('Mathematics')
            ->assertDontSeeText('Science');
    }

    public function test_teacher_can_only_view_official_learners_from_owned_loads(): void
    {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $otherTeacher = $this->createUserWithRole(RoleName::Teacher->value);
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 8']);
        $schoolYear = SchoolYear::factory()->create(['name' => '2039-2040']);

        $ownedSection = Section::factory()->create([
            'name' => 'Section Diamond',
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $otherSection = Section::factory()->create([
            'name' => 'Section Emerald',
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);

        $subject = Subject::factory()->create(['name' => 'Araling Panlipunan', 'code' => 'AP-8']);

        $ownedLoad = TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $schoolYear->id,
            'section_id' => $ownedSection->id,
            'subject_id' => $subject->id,
        ]);
        $otherLoad = TeacherLoad::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'school_year_id' => $schoolYear->id,
            'section_id' => $otherSection->id,
            'subject_id' => $subject->id,
        ]);

        $officialActiveLearner = Learner::factory()->create([
            'first_name' => 'Alicia',
            'last_name' => 'Cruz',
            'sex' => LearnerSex::Female,
        ]);
        $officialTransferredLearner = Learner::factory()->create([
            'first_name' => 'Marco',
            'last_name' => 'Reyes',
            'sex' => LearnerSex::Male,
        ]);
        $unofficialLearner = Learner::factory()->create([
            'first_name' => 'Hidden',
            'last_name' => 'Learner',
        ]);
        $otherSectionLearner = Learner::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'Section',
        ]);

        SectionRoster::factory()->create([
            'section_id' => $ownedSection->id,
            'school_year_id' => $schoolYear->id,
            'learner_id' => $officialActiveLearner->id,
            'import_batch_id' => null,
            'enrollment_status' => EnrollmentStatus::Active,
            'is_official' => true,
        ]);
        SectionRoster::factory()->create([
            'section_id' => $ownedSection->id,
            'school_year_id' => $schoolYear->id,
            'learner_id' => $officialTransferredLearner->id,
            'import_batch_id' => null,
            'enrollment_status' => EnrollmentStatus::TransferredOut,
            'is_official' => true,
        ]);
        SectionRoster::factory()->create([
            'section_id' => $ownedSection->id,
            'school_year_id' => $schoolYear->id,
            'learner_id' => $unofficialLearner->id,
            'import_batch_id' => null,
            'is_official' => false,
        ]);
        SectionRoster::factory()->create([
            'section_id' => $otherSection->id,
            'school_year_id' => $schoolYear->id,
            'learner_id' => $otherSectionLearner->id,
            'import_batch_id' => null,
            'is_official' => true,
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.loads.show', $ownedLoad))
            ->assertOk()
            ->assertSeeText('Alicia')
            ->assertSeeText('Marco')
            ->assertDontSeeText('Hidden')
            ->assertDontSeeText('Other');

        $this->get(route('teacher.loads.show', [
            'teacher_load' => $ownedLoad,
            'search' => 'Marco',
            'enrollment_status' => EnrollmentStatus::TransferredOut->value,
        ]))
            ->assertOk()
            ->assertSeeText('Marco')
            ->assertDontSeeText('Alicia');

        $this->get(route('teacher.loads.show', $otherLoad))
            ->assertRedirect(route('access.denied'));
    }

    public function test_returned_submissions_page_filters_owned_returned_records_with_remarks(): void
    {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $otherTeacher = $this->createUserWithRole(RoleName::Teacher->value);
        $adviser = $this->createUserWithRole(RoleName::Adviser->value, [
            'name' => 'Adviser Clarity',
        ]);
        $gradeLevel = GradeLevel::factory()->create(['name' => 'Grade 9']);
        $currentSchoolYear = SchoolYear::factory()->create(['name' => '2040-2041']);
        $pastSchoolYear = SchoolYear::factory()->create(['name' => '2039-2040']);

        $currentSection = Section::factory()->create([
            'name' => 'Section Hope',
            'school_year_id' => $currentSchoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $pastSection = Section::factory()->create([
            'name' => 'Section Resolve',
            'school_year_id' => $pastSchoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $otherSection = Section::factory()->create([
            'name' => 'Section Summit',
            'school_year_id' => $currentSchoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);

        $math = Subject::factory()->create(['name' => 'Mathematics 9', 'code' => 'MATH-9']);
        $science = Subject::factory()->create(['name' => 'Science 9', 'code' => 'SCI-9']);
        $music = Subject::factory()->create(['name' => 'Music 9', 'code' => 'MUS-9']);
        $arts = Subject::factory()->create(['name' => 'Arts 9', 'code' => 'ART-9']);

        $currentLoad = TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $currentSchoolYear->id,
            'section_id' => $currentSection->id,
            'subject_id' => $math->id,
        ]);
        $pastLoad = TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $pastSchoolYear->id,
            'section_id' => $pastSection->id,
            'subject_id' => $science->id,
        ]);
        $otherLoad = TeacherLoad::factory()->create([
            'teacher_id' => $otherTeacher->id,
            'school_year_id' => $currentSchoolYear->id,
            'section_id' => $otherSection->id,
            'subject_id' => $music->id,
        ]);
        $draftLoad = TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $currentSchoolYear->id,
            'section_id' => $currentSection->id,
            'subject_id' => $arts->id,
        ]);

        $currentQuarterOne = GradingPeriod::factory()->create([
            'school_year_id' => $currentSchoolYear->id,
            'quarter' => GradingQuarter::First,
        ]);
        $currentQuarterTwo = GradingPeriod::factory()->create([
            'school_year_id' => $currentSchoolYear->id,
            'quarter' => GradingQuarter::Second,
        ]);
        $pastQuarterOne = GradingPeriod::factory()->create([
            'school_year_id' => $pastSchoolYear->id,
            'quarter' => GradingQuarter::First,
        ]);

        GradeSubmission::factory()->create([
            'teacher_load_id' => $currentLoad->id,
            'grading_period_id' => $currentQuarterOne->id,
            'status' => GradeSubmissionStatus::Returned,
            'adviser_remarks' => 'Recompute the first quarter component totals.',
            'returned_at' => now()->subDays(2),
        ]);
        GradeSubmission::factory()->create([
            'teacher_load_id' => $currentLoad->id,
            'grading_period_id' => $currentQuarterTwo->id,
            'status' => GradeSubmissionStatus::Returned,
            'adviser_remarks' => 'Check the transmutation sheet for quarter two.',
            'returned_at' => now()->subDay(),
        ]);
        GradeSubmission::factory()->create([
            'teacher_load_id' => $pastLoad->id,
            'grading_period_id' => $pastQuarterOne->id,
            'status' => GradeSubmissionStatus::Returned,
            'adviser_remarks' => 'Past year correction request.',
            'returned_at' => now()->subDays(5),
        ]);
        GradeSubmission::factory()->create([
            'teacher_load_id' => $draftLoad->id,
            'grading_period_id' => $currentQuarterOne->id,
            'status' => GradeSubmissionStatus::Draft,
            'adviser_remarks' => 'Draft should not be shown.',
        ]);
        GradeSubmission::factory()->create([
            'teacher_load_id' => $otherLoad->id,
            'grading_period_id' => $currentQuarterOne->id,
            'status' => GradeSubmissionStatus::Returned,
            'adviser_remarks' => 'Other teacher correction request.',
            'returned_at' => now()->subHours(12),
        ]);

        $this->actingAs($teacher)
            ->get(route('teacher.returned-submissions.index'))
            ->assertOk()
            ->assertSeeText('Recompute the first quarter component totals.')
            ->assertSeeText('Check the transmutation sheet for quarter two.')
            ->assertSeeText('Past year correction request.')
            ->assertDontSeeText('Draft should not be shown.')
            ->assertDontSeeText('Other teacher correction request.');

        $this->get(route('teacher.returned-submissions.index', [
            'school_year_id' => $currentSchoolYear->id,
            'grading_period_id' => $currentQuarterTwo->id,
            'search' => 'transmutation',
        ]))
            ->assertOk()
            ->assertSeeText('Check the transmutation sheet for quarter two.')
            ->assertDontSeeText('Recompute the first quarter component totals.')
            ->assertDontSeeText('Past year correction request.');
    }

    public function test_non_teacher_roles_are_redirected_from_teacher_work_area_routes(): void
    {
        $teacher = $this->createUserWithRole(RoleName::Teacher->value);
        $adviser = $this->createUserWithRole(RoleName::Adviser->value);
        $gradeLevel = GradeLevel::factory()->create();
        $schoolYear = SchoolYear::factory()->create();
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

        foreach ([RoleName::Admin, RoleName::Adviser, RoleName::Registrar] as $role) {
            $this->actingAs($this->createUserWithRole($role->value));

            $this->get(route('teacher.dashboard'))
                ->assertRedirect(route('access.denied'));

            $this->get(route('teacher.loads.index'))
                ->assertRedirect(route('access.denied'));

            $this->get(route('teacher.loads.show', $teacherLoad))
                ->assertRedirect(route('access.denied'));

            $this->get(route('teacher.returned-submissions.index'))
                ->assertRedirect(route('access.denied'));
        }
    }

    private function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
