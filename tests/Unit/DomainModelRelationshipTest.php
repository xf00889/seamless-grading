<?php

namespace Tests\Unit;

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
use App\Enums\TemplateAuditAction;
use App\Enums\TemplateDocumentType;
use App\Models\ApprovalLog;
use App\Models\GradeChangeLog;
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
            'year_end_status' => LearnerYearEndStatus::Promoted,
            'year_end_status_set_by' => $adviser->id,
            'year_end_status_set_at' => now(),
        ]);
        $learnerStatusAuditLog = LearnerStatusAuditLog::factory()->create([
            'section_roster_id' => $sectionRoster->id,
            'acted_by' => $adviser->id,
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
            'grade_level_id' => $gradeLevel->id,
            'scope_key' => 'grade-level:'.$gradeLevel->id,
        ]);
        $templateFieldMap = TemplateFieldMap::factory()->create([
            'template_id' => $template->id,
        ]);
        $templateAuditLog = TemplateAuditLog::factory()->create([
            'template_id' => $template->id,
            'acted_by' => $adviser->id,
        ]);
        $gradingSheetExport = GradingSheetExport::factory()->create([
            'teacher_load_id' => $teacherLoad->id,
            'grading_period_id' => $gradingPeriod->id,
            'template_id' => $template->id,
            'exported_by' => $teacher->id,
        ]);
        $gradingSheetExportAuditLog = GradingSheetExportAuditLog::factory()->create([
            'grading_sheet_export_id' => $gradingSheetExport->id,
            'acted_by' => $teacher->id,
        ]);
        $reportCardRecord = ReportCardRecord::factory()->create([
            'section_roster_id' => $sectionRoster->id,
            'section_id' => $section->id,
            'school_year_id' => $schoolYear->id,
            'learner_id' => $learner->id,
            'grading_period_id' => $gradingPeriod->id,
            'template_id' => $template->id,
            'generated_by' => $adviser->id,
        ]);
        $reportCardRecordAuditLog = ReportCardRecordAuditLog::factory()->create([
            'report_card_record_id' => $reportCardRecord->id,
            'acted_by' => $adviser->id,
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
        $this->assertTrue($sectionRoster->learnerStatusAuditLogs->contains($learnerStatusAuditLog));
        $this->assertTrue($importBatch->rows->contains($importRow));
        $this->assertTrue($teacherLoad->schoolYear->is($schoolYear));
        $this->assertTrue($teacherLoad->gradeSubmissions->contains($gradeSubmission));
        $this->assertTrue($gradeSubmission->quarterlyGrades->contains($quarterlyGrade));
        $this->assertTrue($quarterlyGrade->sectionRoster->is($sectionRoster));
        $this->assertTrue($quarterlyGrade->sectionRoster->learner->is($learner));
        $this->assertTrue($quarterlyGrade->gradeChangeLogs->contains($gradeChangeLog));
        $this->assertTrue($gradeLevel->templates->contains($template));
        $this->assertTrue($template->fieldMaps->contains($templateFieldMap));
        $this->assertTrue($template->auditLogs->contains($templateAuditLog));
        $this->assertTrue($template->gradingSheetExports->contains($gradingSheetExport));
        $this->assertTrue($gradingSheetExport->auditLogs->contains($gradingSheetExportAuditLog));
        $this->assertTrue($template->reportCardRecords->contains($reportCardRecord));
        $this->assertTrue($reportCardRecord->auditLogs->contains($reportCardRecordAuditLog));
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
        $sectionRoster = SectionRoster::factory()->create([
            'year_end_status' => LearnerYearEndStatus::Dropped,
        ]);
        $learnerStatusAuditLog = LearnerStatusAuditLog::factory()->create([
            'section_roster_id' => $sectionRoster->id,
            'action' => LearnerStatusAuditAction::YearEndStatusUpdated,
            'metadata' => ['entity_type' => SectionRoster::class],
        ]);
        $templateAuditLog = TemplateAuditLog::factory()->create([
            'template_id' => $template->id,
            'action' => TemplateAuditAction::Activated,
            'metadata' => ['entity_type' => Template::class],
        ]);
        $gradingSheetExport = GradingSheetExport::factory()->create([
            'template_id' => $template->id,
        ]);
        $gradingSheetExportAuditLog = GradingSheetExportAuditLog::factory()->create([
            'grading_sheet_export_id' => $gradingSheetExport->id,
            'action' => GradingSheetExportAuditAction::Exported,
            'metadata' => ['entity_type' => GradingSheetExport::class],
        ]);
        $reportCardRecord = ReportCardRecord::factory()->create([
            'template_id' => Template::factory()->state([
                'document_type' => TemplateDocumentType::Sf10,
            ]),
            'document_type' => TemplateDocumentType::Sf10,
        ]);
        $reportCardRecordAuditLog = ReportCardRecordAuditLog::factory()->create([
            'report_card_record_id' => $reportCardRecord->id,
            'action' => ReportCardRecordAuditAction::Finalized,
            'metadata' => ['entity_type' => ReportCardRecord::class],
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
        $this->assertInstanceOf(LearnerYearEndStatus::class, $sectionRoster->fresh()->year_end_status);
        $this->assertInstanceOf(LearnerStatusAuditAction::class, $learnerStatusAuditLog->fresh()->action);
        $this->assertInstanceOf(GradeSubmissionStatus::class, $submission->fresh()->status);
        $this->assertInstanceOf(TemplateDocumentType::class, $template->fresh()->document_type);
        $this->assertInstanceOf(TemplateDocumentType::class, $reportCardRecord->fresh()->document_type);
        $this->assertInstanceOf(TemplateAuditAction::class, $templateAuditLog->fresh()->action);
        $this->assertIsArray($templateAuditLog->fresh()->metadata);
        $this->assertInstanceOf(GradingSheetExportAuditAction::class, $gradingSheetExportAuditLog->fresh()->action);
        $this->assertIsArray($gradingSheetExportAuditLog->fresh()->metadata);
        $this->assertInstanceOf(ReportCardRecordAuditAction::class, $reportCardRecordAuditLog->fresh()->action);
        $this->assertIsArray($reportCardRecordAuditLog->fresh()->metadata);
        $this->assertInstanceOf(ApprovalAction::class, $approvalLog->fresh()->action);
        $this->assertIsArray($approvalLog->fresh()->metadata);
        $this->assertIsArray($setting->fresh()->value);
    }
}
