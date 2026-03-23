<?php

namespace Tests\Unit\TemplateManagement;

use App\Enums\TemplateDocumentType;
use App\Services\TemplateManagement\TemplateWorkbookInspectionService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TemplateWorkbookInspectionServiceTest extends TestCase
{
    public function test_it_detects_the_real_card_workbook_as_a_deped_card_template(): void
    {
        $inspection = app(TemplateWorkbookInspectionService::class)->inspectPath(
            base_path('tests/Fixtures/DepEd/CARD 1.xlsx'),
            TemplateDocumentType::Sf9,
        );

        $this->assertSame('recognized', $inspection['status']);
        $this->assertSame('deped_card', $inspection['profile']);
        $this->assertSame("DepEd CARD template", $inspection['detected_document_label']);
        $this->assertSame(['CARD BLANK INSIDE', 'CARD BLANK FRONT'], $inspection['sheet_names']);
        $this->assertSame('CARD BLANK FRONT', $inspection['sheet_roles']['front'] ?? null);
        $this->assertSame('CARD BLANK INSIDE', $inspection['sheet_roles']['inside'] ?? null);
        $this->assertTrue($inspection['is_positional_template']);
    }

    public function test_it_detects_the_real_sf10_workbook_as_a_deped_sf10_template(): void
    {
        $inspection = app(TemplateWorkbookInspectionService::class)->inspectPath(
            base_path('tests/Fixtures/DepEd/School-Form-10-ES-Learners-Academic Permanent-Record_26March2025.xlsx'),
            TemplateDocumentType::Sf10,
        );

        $this->assertSame('recognized', $inspection['status']);
        $this->assertSame('deped_sf10', $inspection['profile']);
        $this->assertSame('DepEd SF10-ES template', $inspection['detected_document_label']);
        $this->assertSame(['Front', 'Back', 'Sir Wedz Helper Table'], $inspection['sheet_names']);
        $this->assertTrue($inspection['is_positional_template']);
    }

    public function test_it_flags_a_real_mismatched_deped_workbook_type(): void
    {
        $inspection = app(TemplateWorkbookInspectionService::class)->inspectPath(
            base_path('tests/Fixtures/DepEd/School-Form-10-ES-Learners-Academic Permanent-Record_26March2025.xlsx'),
            TemplateDocumentType::Sf9,
        );

        $this->assertSame('mismatch', $inspection['status']);
        $this->assertSame('DepEd SF10-ES template', $inspection['detected_document_label']);
        $this->assertNotEmpty($inspection['issues']);
    }

    public function test_it_keeps_simple_workbooks_as_generic_template_files(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'generic-template');
        $xlsxPath = $tempPath.'.xlsx';
        rename($tempPath, $xlsxPath);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Simple Template');
        (new Xlsx($spreadsheet))->save($xlsxPath);

        $inspection = app(TemplateWorkbookInspectionService::class)->inspectPath(
            $xlsxPath,
            TemplateDocumentType::Sf9,
        );

        @unlink($xlsxPath);

        $this->assertSame('generic', $inspection['status']);
        $this->assertSame('generic', $inspection['profile']);
        $this->assertSame(['Simple Template'], $inspection['sheet_names']);
        $this->assertFalse($inspection['is_positional_template']);
        $this->assertTrue($inspection['ready_for_mapping']);
    }
}
