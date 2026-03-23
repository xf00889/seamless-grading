<?php

namespace Tests\Feature\Database;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeSubmissionStatus;
use App\Enums\GradingQuarter;
use App\Enums\RoleName;
use App\Enums\TemplateDocumentType;
use App\Models\GradeSubmission;
use App\Models\GradingSheetExport;
use App\Models\ImportBatch;
use App\Models\ReportCardRecord;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\UatDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UatDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_uat_demo_seeder_builds_realistic_role_based_demo_data(): void
    {
        Storage::fake('local');

        $this->seed(UatDemoSeeder::class);

        $admin = User::query()->where('email', 'admin.uat@example.test')->firstOrFail();
        $teacher = User::query()->where('email', 'teacher.uat@example.test')->firstOrFail();
        $adviser = User::query()->where('email', 'adviser.uat@example.test')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.uat@example.test')->firstOrFail();

        $this->assertTrue($admin->hasRole(RoleName::Admin->value));
        $this->assertTrue($teacher->hasRole(RoleName::Teacher->value));
        $this->assertTrue($adviser->hasRole(RoleName::Adviser->value));
        $this->assertTrue($registrar->hasRole(RoleName::Registrar->value));

        $schoolYear = SchoolYear::query()->where('name', '2025-2026')->firstOrFail();
        $this->assertTrue($schoolYear->is_active);
        $this->assertDatabaseHas('grading_periods', [
            'school_year_id' => $schoolYear->id,
            'quarter' => GradingQuarter::Second->value,
            'is_open' => true,
        ]);

        $narra = Section::query()->where('name', 'Narra')->where('school_year_id', $schoolYear->id)->firstOrFail();
        $this->assertSame($adviser->id, $narra->adviser_id);

        $this->assertDatabaseHas('section_rosters', [
            'section_id' => $narra->id,
            'enrollment_status' => EnrollmentStatus::TransferredOut->value,
        ]);
        $this->assertDatabaseHas('section_rosters', [
            'section_id' => $narra->id,
            'enrollment_status' => EnrollmentStatus::Dropped->value,
        ]);

        $this->assertDatabaseHas('grade_submissions', ['status' => GradeSubmissionStatus::Draft->value]);
        $this->assertDatabaseHas('grade_submissions', ['status' => GradeSubmissionStatus::Submitted->value]);
        $this->assertDatabaseHas('grade_submissions', ['status' => GradeSubmissionStatus::Returned->value]);
        $this->assertDatabaseHas('grade_submissions', ['status' => GradeSubmissionStatus::Approved->value]);
        $this->assertDatabaseHas('grade_submissions', ['status' => GradeSubmissionStatus::Locked->value]);
        $this->assertDatabaseHas('approval_logs', ['action' => 'reopened']);

        $confirmedImport = ImportBatch::query()->whereNotNull('confirmed_at')->firstOrFail();
        $this->assertSame($admin->id, $confirmedImport->confirmed_by);
        Storage::disk('local')->assertExists($confirmedImport->source_path);

        $activeTemplates = Template::query()->where('is_active', true)->get();
        $this->assertCount(3, $activeTemplates);

        foreach ($activeTemplates as $template) {
            Storage::disk('local')->assertExists($template->file_path);
        }

        $this->assertDatabaseHas('report_card_records', [
            'document_type' => TemplateDocumentType::Sf9->value,
            'is_finalized' => true,
        ]);
        $this->assertDatabaseHas('report_card_records', [
            'document_type' => TemplateDocumentType::Sf10->value,
            'is_finalized' => true,
        ]);

        $gradingSheetExport = GradingSheetExport::query()->where('version', 2)->firstOrFail();
        Storage::disk('local')->assertExists($gradingSheetExport->file_path);

        foreach (ReportCardRecord::query()->where('is_finalized', true)->get() as $record) {
            Storage::disk('local')->assertExists($record->file_path);
        }

        $this->assertGreaterThanOrEqual(7, GradeSubmission::query()->count());
        $this->assertDatabaseCount('grading_sheet_exports', 2);
    }
}
