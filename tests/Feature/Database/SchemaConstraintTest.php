<?php

namespace Tests\Feature\Database;

use App\Enums\GradingQuarter;
use App\Enums\TemplateDocumentType;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\GradingSheetExport;
use App\Models\GradingSheetExportAuditLog;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Learner;
use App\Models\LearnerStatusAuditLog;
use App\Models\QuarterlyGrade;
use App\Models\ReportCardRecord;
use App\Models\ReportCardRecordAuditLog;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\Subject;
use App\Models\SystemSetting;
use App\Models\TeacherLoad;
use App\Models\Template;
use App\Models\TemplateAuditLog;
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

    public function test_templates_enforce_unique_document_type_scope_code_and_version_pairs(): void
    {
        $gradeLevel = GradeLevel::factory()->create();

        Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevel->id,
            'scope_key' => 'grade-level:'.$gradeLevel->id,
            'version' => 1,
        ]);

        $this->expectException(QueryException::class);

        Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevel->id,
            'scope_key' => 'grade-level:'.$gradeLevel->id,
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

    public function test_templates_allow_reusing_a_code_and_version_across_different_scopes(): void
    {
        $gradeLevelA = GradeLevel::factory()->create();
        $gradeLevelB = GradeLevel::factory()->create();

        Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevelA->id,
            'scope_key' => 'grade-level:'.$gradeLevelA->id,
            'version' => 1,
        ]);

        Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevelB->id,
            'scope_key' => 'grade-level:'.$gradeLevelB->id,
            'version' => 1,
        ]);

        $this->assertDatabaseCount('templates', 2);
    }

    public function test_templates_enforce_only_one_active_version_per_document_type_and_scope(): void
    {
        $gradeLevel = GradeLevel::factory()->create();

        Template::factory()->create([
            'code' => 'report-card',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevel->id,
            'scope_key' => 'grade-level:'.$gradeLevel->id,
            'is_active' => true,
            'active_scope_key' => 'sf9:grade-level:'.$gradeLevel->id,
        ]);

        $this->expectException(QueryException::class);

        Template::factory()->create([
            'code' => 'report-card-alt',
            'document_type' => TemplateDocumentType::Sf9,
            'grade_level_id' => $gradeLevel->id,
            'scope_key' => 'grade-level:'.$gradeLevel->id,
            'is_active' => true,
            'active_scope_key' => 'sf9:grade-level:'.$gradeLevel->id,
        ]);
    }

    public function test_report_card_records_enforce_unique_roster_period_document_type_and_version_pairs(): void
    {
        $reportCardRecord = ReportCardRecord::factory()->create([
            'template_id' => Template::factory()->state([
                'document_type' => TemplateDocumentType::Sf10,
            ]),
            'document_type' => TemplateDocumentType::Sf10,
        ]);

        $this->expectException(QueryException::class);

        ReportCardRecord::factory()->create([
            'section_roster_id' => $reportCardRecord->section_roster_id,
            'section_id' => $reportCardRecord->section_id,
            'school_year_id' => $reportCardRecord->school_year_id,
            'learner_id' => $reportCardRecord->learner_id,
            'grading_period_id' => $reportCardRecord->grading_period_id,
            'template_id' => Template::factory()->state([
                'document_type' => TemplateDocumentType::Sf10,
            ]),
            'document_type' => TemplateDocumentType::Sf10,
            'record_version' => $reportCardRecord->record_version,
        ]);
    }

    public function test_report_card_records_allow_reusing_a_record_version_for_the_same_roster_period_across_sf9_and_sf10(): void
    {
        $reportCardRecord = ReportCardRecord::factory()->create([
            'document_type' => TemplateDocumentType::Sf9,
        ]);

        ReportCardRecord::factory()->create([
            'section_roster_id' => $reportCardRecord->section_roster_id,
            'section_id' => $reportCardRecord->section_id,
            'school_year_id' => $reportCardRecord->school_year_id,
            'learner_id' => $reportCardRecord->learner_id,
            'grading_period_id' => $reportCardRecord->grading_period_id,
            'template_id' => Template::factory()->state([
                'document_type' => TemplateDocumentType::Sf10,
            ]),
            'document_type' => TemplateDocumentType::Sf10,
            'record_version' => $reportCardRecord->record_version,
        ]);

        $this->assertDatabaseCount('report_card_records', 2);
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

    public function test_template_audit_logs_are_deleted_when_their_template_is_deleted(): void
    {
        $template = Template::factory()->create();
        $auditLog = TemplateAuditLog::factory()->create([
            'template_id' => $template->id,
        ]);

        $template->delete();

        $this->assertDatabaseMissing('template_audit_logs', [
            'id' => $auditLog->id,
        ]);
    }

    public function test_grading_sheet_export_audit_logs_are_deleted_when_their_export_is_deleted(): void
    {
        $gradingSheetExport = GradingSheetExport::factory()->create();
        $auditLog = GradingSheetExportAuditLog::factory()->create([
            'grading_sheet_export_id' => $gradingSheetExport->id,
        ]);

        $gradingSheetExport->delete();

        $this->assertDatabaseMissing('grading_sheet_export_audit_logs', [
            'id' => $auditLog->id,
        ]);
    }

    public function test_report_card_record_audit_logs_are_deleted_when_their_record_is_deleted(): void
    {
        $reportCardRecord = ReportCardRecord::factory()->create();
        $auditLog = ReportCardRecordAuditLog::factory()->create([
            'report_card_record_id' => $reportCardRecord->id,
        ]);

        $reportCardRecord->delete();

        $this->assertDatabaseMissing('report_card_record_audit_logs', [
            'id' => $auditLog->id,
        ]);
    }

    public function test_learner_status_audit_logs_are_deleted_when_their_roster_is_deleted(): void
    {
        $sectionRoster = SectionRoster::factory()->create();
        $auditLog = LearnerStatusAuditLog::factory()->create([
            'section_roster_id' => $sectionRoster->id,
        ]);

        $sectionRoster->delete();

        $this->assertDatabaseMissing('learner_status_audit_logs', [
            'id' => $auditLog->id,
        ]);
    }
}
