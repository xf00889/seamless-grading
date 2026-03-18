<?php

namespace Tests\Feature\Database;

use App\Enums\GradingQuarter;
use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Learner;
use App\Models\QuarterlyGrade;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\Subject;
use App\Models\SystemSetting;
use App\Models\TeacherLoad;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_loads_enforce_unique_teacher_school_year_section_and_subject_assignments(): void
    {
        $schoolYear = SchoolYear::factory()->create();
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
        ]);
        $subject = Subject::factory()->create();
        $teacher = User::factory()->create();

        TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);

        $this->expectException(QueryException::class);

        TeacherLoad::factory()->create([
            'teacher_id' => $teacher->id,
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_teacher_loads_allow_distinct_teachers_for_the_same_school_year_section_and_subject(): void
    {
        $section = Section::factory()->create();
        $subject = Subject::factory()->create();

        TeacherLoad::factory()->create([
            'teacher_id' => User::factory()->create()->id,
            'school_year_id' => $section->school_year_id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);

        TeacherLoad::factory()->create([
            'teacher_id' => User::factory()->create()->id,
            'school_year_id' => $section->school_year_id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertDatabaseCount('teacher_loads', 2);
    }

    public function test_sections_cannot_be_deleted_while_teacher_loads_reference_them(): void
    {
        $section = Section::factory()->create();

        TeacherLoad::factory()->create([
            'school_year_id' => $section->school_year_id,
            'section_id' => $section->id,
        ]);

        $this->expectException(QueryException::class);

        $section->delete();
    }

    public function test_teacher_loads_require_their_school_year_to_match_the_section_school_year(): void
    {
        $sectionSchoolYear = SchoolYear::factory()->create();
        $differentSchoolYear = SchoolYear::factory()->create();
        $section = Section::factory()->create([
            'school_year_id' => $sectionSchoolYear->id,
        ]);

        $this->expectException(QueryException::class);

        TeacherLoad::factory()->create([
            'school_year_id' => $differentSchoolYear->id,
            'section_id' => $section->id,
        ]);
    }

    public function test_section_rosters_enforce_one_learner_per_school_year(): void
    {
        $schoolYear = SchoolYear::factory()->create();
        $gradeLevel = GradeLevel::factory()->create();
        $learner = Learner::factory()->create();

        SectionRoster::factory()->create([
            'school_year_id' => $schoolYear->id,
            'section_id' => Section::factory()->create([
                'school_year_id' => $schoolYear->id,
                'grade_level_id' => $gradeLevel->id,
            ])->id,
            'learner_id' => $learner->id,
        ]);

        $this->expectException(QueryException::class);

        SectionRoster::factory()->create([
            'school_year_id' => $schoolYear->id,
            'section_id' => Section::factory()->create([
                'school_year_id' => $schoolYear->id,
                'grade_level_id' => $gradeLevel->id,
            ])->id,
            'learner_id' => $learner->id,
        ]);
    }

    public function test_section_rosters_require_their_school_year_to_match_the_section_school_year(): void
    {
        $sectionSchoolYear = SchoolYear::factory()->create();
        $differentSchoolYear = SchoolYear::factory()->create();
        $section = Section::factory()->create([
            'school_year_id' => $sectionSchoolYear->id,
        ]);

        $this->expectException(QueryException::class);

        SectionRoster::factory()->create([
            'school_year_id' => $differentSchoolYear->id,
            'section_id' => $section->id,
        ]);
    }

    public function test_grade_submissions_enforce_one_submission_per_teacher_load_and_grading_period(): void
    {
        $schoolYear = SchoolYear::factory()->create();
        $gradingPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::First,
        ]);
        $teacherLoad = TeacherLoad::factory()->create([
            'section_id' => Section::factory()->create([
                'school_year_id' => $schoolYear->id,
            ])->id,
        ]);

        GradeSubmission::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
            'grading_period_id' => $gradingPeriod->id,
        ]);

        $this->expectException(QueryException::class);

        GradeSubmission::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
            'grading_period_id' => $gradingPeriod->id,
        ]);
    }

    public function test_quarterly_grades_enforce_unique_submission_and_section_roster_pairs(): void
    {
        $schoolYear = SchoolYear::factory()->create();
        $gradingPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::Second,
        ]);
        $gradeLevel = GradeLevel::factory()->create();
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
        ]);
        $teacherLoad = TeacherLoad::factory()->create([
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
        ]);
        $submission = GradeSubmission::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
            'grading_period_id' => $gradingPeriod->id,
        ]);
        $roster = SectionRoster::factory()->create([
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
        ]);

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $roster->id,
        ]);

        $this->expectException(QueryException::class);

        QuarterlyGrade::factory()->create([
            'grade_submission_id' => $submission->id,
            'section_roster_id' => $roster->id,
        ]);
    }

    public function test_templates_enforce_unique_document_type_code_and_version_pairs(): void
    {
        Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf9,
            'version' => 1,
        ]);

        $this->expectException(QueryException::class);

        Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf9,
            'version' => 1,
        ]);
    }

    public function test_templates_allow_reusing_a_code_and_version_across_different_document_types(): void
    {
        Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf9,
            'version' => 1,
        ]);

        Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf10,
            'version' => 1,
        ]);

        $this->assertDatabaseCount('templates', 2);
    }

    public function test_system_settings_enforce_unique_keys(): void
    {
        SystemSetting::factory()->create([
            'key' => 'academic.active_school_year',
        ]);

        $this->expectException(QueryException::class);

        SystemSetting::factory()->create([
            'key' => 'academic.active_school_year',
        ]);
    }

    public function test_import_batch_rows_are_deleted_when_their_batch_is_deleted(): void
    {
        $batch = ImportBatch::factory()->create();
        $row = ImportBatchRow::factory()->create([
            'import_batch_id' => $batch->id,
        ]);

        $batch->delete();

        $this->assertDatabaseMissing('import_batch_rows', [
            'id' => $row->id,
        ]);
    }
}
