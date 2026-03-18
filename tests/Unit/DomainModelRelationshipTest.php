<?php

namespace Tests\Unit;

use App\Enums\ApprovalAction;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Enums\ImportBatchRowStatus;
use App\Enums\ImportBatchStatus;
use App\Enums\LearnerSex;
use App\Enums\TemplateDocumentType;
use App\Models\ApprovalLog;
use App\Models\GradeChangeLog;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\GradingPeriod;
use App\Models\GradingSheetExport;
use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use App\Models\Learner;
use App\Models\QuarterlyGrade;
use App\Models\ReportCardRecord;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Models\Subject;
use App\Models\SystemSetting;
use App\Models\TeacherLoad;
use App\Models\Template;
use App\Models\TemplateFieldMap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_models_expose_expected_relationships(): void
    {
        $schoolYear = SchoolYear::factory()->create();
        $gradeLevel = GradeLevel::factory()->create();
        $adviser = User::factory()->create();
        $teacher = User::factory()->create();
        $learner = Learner::factory()->create();
        $section = Section::factory()->create([
            'school_year_id' => $schoolYear->id,
            'grade_level_id' => $gradeLevel->id,
            'adviser_id' => $adviser->id,
        ]);
        $subject = Subject::factory()->create();
        $teacherLoad = TeacherLoad::factory()->create([
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
        $gradingPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::First,
        ]);
        $importBatch = ImportBatch::factory()->create([
            'section_id' => $section->id,
            'imported_by' => $adviser->id,
        ]);
        $importRow = ImportBatchRow::factory()->create([
            'import_batch_id' => $importBatch->id,
            'learner_id' => $learner->id,
        ]);
        $sectionRoster = SectionRoster::factory()->create([
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'learner_id' => $learner->id,
            'import_batch_id' => $importBatch->id,
        ]);
        $gradeSubmission = GradeSubmission::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
            'grading_period_id' => $gradingPeriod->id,
            'submitted_by' => $teacher->id,
        ]);
        $quarterlyGrade = QuarterlyGrade::factory()->create([
            'grade_submission_id' => $gradeSubmission->id,
            'section_roster_id' => $sectionRoster->id,
        ]);
        $gradeChangeLog = GradeChangeLog::factory()->create([
            'quarterly_grade_id' => $quarterlyGrade->id,
            'changed_by' => $teacher->id,
        ]);
        $template = Template::factory()->create([
            'code' => 'sf9-report-card',
            'document_type' => TemplateDocumentType::Sf9,
        ]);
        $templateFieldMap = TemplateFieldMap::factory()->create([
            'template_id' => $template->id,
        ]);
        $gradingSheetExport = GradingSheetExport::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
            'grading_period_id' => $gradingPeriod->id,
            'template_id' => $template->id,
            'exported_by' => $teacher->id,
        ]);
        $reportCardRecord = ReportCardRecord::factory()->create([
            'section_roster_id' => $sectionRoster->id,
            'grading_period_id' => $gradingPeriod->id,
            'template_id' => $template->id,
            'generated_by' => $adviser->id,
        ]);
        $approvalLog = ApprovalLog::factory()->create([
            'grade_submission_id' => $gradeSubmission->id,
            'acted_by' => $adviser->id,
        ]);
        $systemSetting = SystemSetting::factory()->create();

        $this->assertTrue($schoolYear->gradingPeriods->contains($gradingPeriod));
        $this->assertTrue($schoolYear->teacherLoads->contains($teacherLoad));
        $this->assertTrue($section->teacherLoads->contains($teacherLoad));
        $this->assertTrue($section->importBatches->contains($importBatch));
        $this->assertTrue($learner->sectionRosters->contains($sectionRoster));
        $this->assertTrue($importBatch->rows->contains($importRow));
        $this->assertTrue($teacherLoad->schoolYear->is($schoolYear));
        $this->assertTrue($teacherLoad->gradeSubmissions->contains($gradeSubmission));
        $this->assertTrue($gradeSubmission->quarterlyGrades->contains($quarterlyGrade));
        $this->assertTrue($quarterlyGrade->sectionRoster->is($sectionRoster));
        $this->assertTrue($quarterlyGrade->sectionRoster->learner->is($learner));
        $this->assertTrue($quarterlyGrade->gradeChangeLogs->contains($gradeChangeLog));
        $this->assertTrue($template->fieldMaps->contains($templateFieldMap));
        $this->assertTrue($template->gradingSheetExports->contains($gradingSheetExport));
        $this->assertTrue($template->reportCardRecords->contains($reportCardRecord));
        $this->assertTrue($gradeSubmission->approvalLogs->contains($approvalLog));
        $this->assertIsArray($systemSetting->value);
    }

    public function test_domain_models_apply_expected_enum_and_json_casts(): void
    {
        $schoolYear = SchoolYear::factory()->create();
        $gradingPeriod = GradingPeriod::factory()->create([
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::Fourth,
        ]);
        $learner = Learner::factory()->create([
            'sex' => LearnerSex::Female,
            'enrollment_status' => EnrollmentStatus::TransferredOut,
        ]);
        $importBatch = ImportBatch::factory()->create([
            'status' => ImportBatchStatus::Confirmed,
        ]);
        $importRow = ImportBatchRow::factory()->create([
            'import_batch_id' => $importBatch->id,
            'status' => ImportBatchRowStatus::Imported,
            'payload' => ['lrn' => $learner->lrn],
        ]);
        $teacherLoad = TeacherLoad::factory()->create();
        $submission = GradeSubmission::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
            'grading_period_id' => $gradingPeriod->id,
            'status' => GradeSubmissionStatus::Submitted,
        ]);
        $template = Template::factory()->create([
            'document_type' => TemplateDocumentType::GradingSheet,
        ]);
        $approvalLog = ApprovalLog::factory()->create([
            'grade_submission_id' => $submission->id,
            'action' => ApprovalAction::Approved,
            'metadata' => ['actor' => 'system'],
        ]);
        $setting = SystemSetting::factory()->create([
            'value' => ['school_year_id' => $schoolYear->id],
        ]);

        $this->assertInstanceOf(GradingQuarter::class, $gradingPeriod->fresh()->quarter);
        $this->assertInstanceOf(LearnerSex::class, $learner->fresh()->sex);
        $this->assertInstanceOf(EnrollmentStatus::class, $learner->fresh()->enrollment_status);
        $this->assertInstanceOf(ImportBatchStatus::class, $importBatch->fresh()->status);
        $this->assertInstanceOf(ImportBatchRowStatus::class, $importRow->fresh()->status);
        $this->assertIsArray($importRow->fresh()->payload);
        $this->assertInstanceOf(GradeSubmissionStatus::class, $submission->fresh()->status);
        $this->assertInstanceOf(TemplateDocumentType::class, $template->fresh()->document_type);
        $this->assertInstanceOf(ApprovalAction::class, $approvalLog->fresh()->action);
        $this->assertIsArray($approvalLog->fresh()->metadata);
        $this->assertIsArray($setting->fresh()->value);
    }
}
