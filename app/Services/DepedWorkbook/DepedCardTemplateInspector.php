<?php

namespace App\Services\DepedWorkbook;

use App\Enums\DepedWorkbookDocumentType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DepedCardTemplateInspector extends AbstractDepedWorkbookInspector
{
    public function inspect(Spreadsheet $spreadsheet): ?array
    {
        $insideSheet = $this->firstMatchingSheet($spreadsheet, fn (Worksheet $sheet): bool => (
            str_contains($this->normalizeText($sheet->getTitle()), 'CARD')
            && $this->cellContains($sheet, 'A2', 'REPORT ON LEARNING PROGRESS AND ACHIEVEMENT')
            && $this->cellContains($sheet, 'T2', 'REPORT ON LEARNER')
            && $this->cellContains($sheet, 'A18', 'SECOND SEMESTER')
        ));
        $frontSheet = $this->firstMatchingSheet($spreadsheet, fn (Worksheet $sheet): bool => (
            str_contains($this->normalizeText($sheet->getTitle()), 'CARD')
            && $this->cellContains($sheet, 'U1', 'DepEd Form 138')
            && $this->cellContains($sheet, 'U10', 'STUDENT\'S REPORT CARD')
            && $this->cellContains($sheet, 'B19', 'PARENT/GUARDIAN\'S SIGNATURE')
        ));

        if ($insideSheet === null || $frontSheet === null) {
            return null;
        }

        return [
            'document_type' => DepedWorkbookDocumentType::CardTemplate->value,
            'document_label' => DepedWorkbookDocumentType::CardTemplate->label(),
            'handling_mode' => DepedWorkbookDocumentType::CardTemplate->handlingMode(),
            'template_document_type_hint' => DepedWorkbookDocumentType::CardTemplate->templateDocumentTypeHint(),
            ...$this->workbookSummary($spreadsheet),
            'source_sheet_name' => $insideSheet->getTitle(),
            'sheet_roles' => [
                'front' => $frontSheet->getTitle(),
                'inside' => $insideSheet->getTitle(),
            ],
            'anchors' => [
                'inside' => [
                    'A2' => $this->cellDisplayValue($insideSheet, 'A2'),
                    'A3' => $this->cellDisplayValue($insideSheet, 'A3'),
                    'A18' => $this->cellDisplayValue($insideSheet, 'A18'),
                    'B34' => $this->cellDisplayValue($insideSheet, 'B34'),
                ],
                'front' => [
                    'U1' => $this->cellDisplayValue($frontSheet, 'U1'),
                    'U10' => $this->cellDisplayValue($frontSheet, 'U10'),
                    'B19' => $this->cellDisplayValue($frontSheet, 'B19'),
                    'U19' => $this->cellDisplayValue($frontSheet, 'U19'),
                ],
            ],
            'structural_readiness' => [
                'is_positional_template' => true,
                'ready_for_mapping' => true,
                'has_inside_sheet' => true,
                'has_front_sheet' => true,
            ],
            'rows' => [],
        ];
    }
}
