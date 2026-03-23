<?php

namespace Tests\Unit\TemplateManagement;

use App\Enums\TemplateDocumentType;
use App\Services\TemplateManagement\TemplateWorkbookInspectionService;
use App\Services\TemplateManagement\TemplateWorksheetSuggestionService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TemplateWorksheetSuggestionServiceTest extends TestCase
{
    public function test_it_suggests_front_and_inside_sheet_ownership_for_the_real_card_fixture(): void
    {
        $inspection = app(TemplateWorkbookInspectionService::class)->inspectPath(
            base_path('tests/Fixtures/DepEd/CARD 1.xlsx'),
            TemplateDocumentType::Sf9,
        );

        $suggestions = app(TemplateWorksheetSuggestionService::class)->suggestionsFor(
            TemplateDocumentType::Sf9,
            $inspection,
        );

        $this->assertSame('CARD BLANK FRONT', $suggestions['school_name'] ?? null);
        $this->assertSame('CARD BLANK FRONT', $suggestions['learner_name'] ?? null);
        $this->assertSame('CARD BLANK FRONT', $suggestions['learner_lrn'] ?? null);
        $this->assertSame('CARD BLANK INSIDE', $suggestions['subject_name_column'] ?? null);
        $this->assertSame('CARD BLANK INSIDE', $suggestions['subject_grade_column'] ?? null);
        $this->assertSame('CARD BLANK INSIDE', $suggestions['general_average'] ?? null);
    }

    public function test_it_uses_semantically_detected_card_sheet_roles_when_titles_differ(): void
    {
        $path = $this->temporaryCardWorkbookPath(
            frontTitle: 'CARD FRONT ALT',
            insideTitle: 'CARD INSIDE ALT',
        );

        $inspection = app(TemplateWorkbookInspectionService::class)->inspectPath(
            $path,
            TemplateDocumentType::Sf9,
        );

        $suggestions = app(TemplateWorksheetSuggestionService::class)->suggestionsFor(
            TemplateDocumentType::Sf9,
            $inspection,
        );

        @unlink($path);

        $this->assertSame('recognized', $inspection['status']);
        $this->assertSame('deped_card', $inspection['profile']);
        $this->assertSame('CARD FRONT ALT', $suggestions['school_name'] ?? null);
        $this->assertSame('CARD INSIDE ALT', $suggestions['subject_name_column'] ?? null);
    }

    public function test_it_does_not_suggest_sheet_ownership_for_unrecognized_generic_sf9_workbooks(): void
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

        $suggestions = app(TemplateWorksheetSuggestionService::class)->suggestionsFor(
            TemplateDocumentType::Sf9,
            $inspection,
        );

        @unlink($xlsxPath);

        $this->assertSame([], $suggestions);
    }

    private function temporaryCardWorkbookPath(string $frontTitle, string $insideTitle): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'card-template');
        $xlsxPath = $tempPath.'.xlsx';
        rename($tempPath, $xlsxPath);

        $spreadsheet = new Spreadsheet;
        $insideSheet = $spreadsheet->getActiveSheet();
        $insideSheet->setTitle($insideTitle);
        $insideSheet->setCellValue('A2', 'REPORT ON LEARNING PROGRESS AND ACHIEVEMENT');
        $insideSheet->setCellValue('T2', 'REPORT ON LEARNER');
        $insideSheet->setCellValue('A18', 'SECOND SEMESTER');

        $frontSheet = $spreadsheet->createSheet();
        $frontSheet->setTitle($frontTitle);
        $frontSheet->setCellValue('U1', 'DepEd Form 138');
        $frontSheet->setCellValue('U10', "STUDENT'S REPORT CARD");
        $frontSheet->setCellValue('B19', "PARENT/GUARDIAN'S SIGNATURE");

        (new Xlsx($spreadsheet))->save($xlsxPath);

        return $xlsxPath;
    }
}
