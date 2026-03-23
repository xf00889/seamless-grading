<?php

namespace Tests\Unit\Sf1Import;

use App\Enums\DepedWorkbookDocumentType;
use App\Services\DepedWorkbook\DepedWorkbookInspectionService;
use App\Services\DepedWorkbook\UnsupportedDepedWorkbookException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DepedWorkbookInspectionServiceTest extends TestCase
{
    public function test_it_detects_the_real_sf1_fixture_and_extracts_metadata_and_normalized_rows(): void
    {
        $inspection = app(DepedWorkbookInspectionService::class)
            ->inspectPath($this->fixturePath('School Form 1 (SF 1) (7).xls'));

        $this->assertSame(DepedWorkbookDocumentType::Sf1ShsRosterImport->value, $inspection['document_type']);
        $this->assertSame('learner_import', $inspection['handling_mode']);
        $this->assertSame('school_form_1_shs_ver2018.2.1.1', $inspection['source_sheet_name']);
        $this->assertSame('Culipapa National High School', $inspection['metadata']['school_name']);
        $this->assertSame('302614', $inspection['metadata']['school_id']);
        $this->assertSame('First Semester', $inspection['metadata']['semester']);
        $this->assertSame('2023 - 2024', $inspection['metadata']['school_year']);
        $this->assertSame('GAUTAMA A', $inspection['metadata']['section']);
        $this->assertCount(45, $inspection['rows']);

        $firstRow = $inspection['rows'][0];

        $this->assertSame(20, $firstRow['row_number']);
        $this->assertSame('117147110007.000', $firstRow['payload']['lrn']);
        $this->assertSame('AMBRONA,REY PACUNLA', $firstRow['payload']['raw_name']);
        $this->assertSame('117147110007', $firstRow['normalized_data']['lrn']);
        $this->assertSame('Ambrona', $firstRow['normalized_data']['last_name']);
        $this->assertSame('Rey', $firstRow['normalized_data']['first_name']);
        $this->assertSame('Pacunla', $firstRow['normalized_data']['middle_name']);
        $this->assertSame('male', $firstRow['normalized_data']['sex']);
        $this->assertSame('2005-06-07', $firstRow['normalized_data']['birth_date']);
        $this->assertSame(18, $firstRow['normalized_data']['age']);
        $this->assertSame('Face to Face', $firstRow['normalized_data']['learning_modality']);
        $this->assertSame('CCT', $firstRow['normalized_data']['remarks']);

        $rowNumbers = collect($inspection['rows'])->pluck('row_number')->all();

        $this->assertContains(39, $rowNumbers);
        $this->assertNotContains(38, $rowNumbers);
        $this->assertNotContains(66, $rowNumbers);
        $this->assertNotContains(67, $rowNumbers);
        $this->assertNotContains(68, $rowNumbers);
    }

    public function test_it_detects_the_sf10_fixture_as_a_template_inspection_workbook(): void
    {
        $inspection = app(DepedWorkbookInspectionService::class)
            ->inspectPath($this->fixturePath('School-Form-10-ES-Learners-Academic Permanent-Record_26March2025.xlsx'));

        $this->assertSame(DepedWorkbookDocumentType::Sf10EsTemplate->value, $inspection['document_type']);
        $this->assertSame('template_inspection', $inspection['handling_mode']);
        $this->assertSame('sf10', $inspection['template_document_type_hint']);
        $this->assertSame(3, $inspection['sheet_count']);
        $this->assertSame(['Front', 'Back', 'Sir Wedz Helper Table'], $inspection['sheet_names']);
        $this->assertTrue($inspection['structural_readiness']['is_positional_template']);
        $this->assertTrue($inspection['structural_readiness']['ready_for_mapping']);
        $this->assertTrue($inspection['structural_readiness']['has_helper_sheet']);
        $this->assertSame([], $inspection['rows']);
    }

    public function test_it_detects_the_card_fixture_as_a_template_inspection_workbook(): void
    {
        $inspection = app(DepedWorkbookInspectionService::class)
            ->inspectPath($this->fixturePath('CARD 1.xlsx'));

        $this->assertSame(DepedWorkbookDocumentType::CardTemplate->value, $inspection['document_type']);
        $this->assertSame('template_inspection', $inspection['handling_mode']);
        $this->assertSame('sf9', $inspection['template_document_type_hint']);
        $this->assertSame(2, $inspection['sheet_count']);
        $this->assertSame(['CARD BLANK INSIDE', 'CARD BLANK FRONT'], $inspection['sheet_names']);
        $this->assertTrue($inspection['structural_readiness']['is_positional_template']);
        $this->assertTrue($inspection['structural_readiness']['ready_for_mapping']);
        $this->assertSame([], $inspection['rows']);
    }

    #[DataProvider('fixtureDetectionProvider')]
    public function test_detection_is_not_limited_to_the_first_sheet(
        string $fixtureName,
        DepedWorkbookDocumentType $expectedDocumentType,
        string $expectedSourceSheet,
    ): void {
        $tempWorkbook = $this->prependDummyFirstSheet($this->fixturePath($fixtureName));

        $inspection = app(DepedWorkbookInspectionService::class)->inspectPath($tempWorkbook);

        $this->assertSame($expectedDocumentType->value, $inspection['document_type']);
        $this->assertSame($expectedSourceSheet, $inspection['source_sheet_name']);
        $this->assertSame('Ignore This Sheet', $inspection['sheet_names'][0]);
    }

    public static function fixtureDetectionProvider(): array
    {
        return [
            'sf1' => [
                'School Form 1 (SF 1) (7).xls',
                DepedWorkbookDocumentType::Sf1ShsRosterImport,
                'school_form_1_shs_ver2018.2.1.1',
            ],
            'sf10' => [
                'School-Form-10-ES-Learners-Academic Permanent-Record_26March2025.xlsx',
                DepedWorkbookDocumentType::Sf10EsTemplate,
                'Front',
            ],
            'card' => [
                'CARD 1.xlsx',
                DepedWorkbookDocumentType::CardTemplate,
                'CARD BLANK INSIDE',
            ],
        ];
    }

    public function test_it_fails_safely_for_unsupported_workbook_structures(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Notes');
        $spreadsheet->getActiveSheet()->setCellValue('A1', 'This is not a DepEd positional form.');
        $path = $this->saveSpreadsheet($spreadsheet);

        $this->expectException(UnsupportedDepedWorkbookException::class);
        $this->expectExceptionMessage('The uploaded workbook structure is not a supported DepEd SF1 roster import, SF10 template, or CARD template layout.');

        app(DepedWorkbookInspectionService::class)->inspectPath($path);
    }

    private function fixturePath(string $fixtureName): string
    {
        return base_path('tests/Fixtures/DepEd/'.$fixtureName);
    }

    private function prependDummyFirstSheet(string $fixturePath): string
    {
        $spreadsheet = IOFactory::load($fixturePath);
        $dummy = $spreadsheet->createSheet(0);
        $dummy->setTitle('Ignore This Sheet');
        $dummy->setCellValue('A1', 'No matching DepEd anchors live here.');

        return $this->saveSpreadsheet($spreadsheet);
    }

    private function saveSpreadsheet(Spreadsheet $spreadsheet): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'deped-workbook');
        $xlsxPath = $tempPath.'.xlsx';

        rename($tempPath, $xlsxPath);

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);

        return $xlsxPath;
    }
}
