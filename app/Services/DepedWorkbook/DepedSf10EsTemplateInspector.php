<?php

namespace App\Services\DepedWorkbook;

use App\Enums\DepedWorkbookDocumentType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class DepedSf10EsTemplateInspector extends AbstractDepedWorkbookInspector
{
    public function inspect(Spreadsheet $spreadsheet): ?array
    {
        $frontSheet = $this->sheetNamed($spreadsheet, 'Front');
        $backSheet = $this->sheetNamed($spreadsheet, 'Back');

        if ($frontSheet === null || $backSheet === null) {
            return null;
        }

        if (
            ! $this->cellContains($frontSheet, 'B1', 'SF10-ES')
            || ! $this->cellContains($frontSheet, 'H5', 'Learner Permanent Academic Record for Elementary School')
            || ! $this->cellContains($frontSheet, 'B21', 'SCHOLASTIC RECORD')
            || ! $this->cellContains($backSheet, 'B1', 'SF10-ES')
            || ! $this->cellContains($backSheet, 'B2', 'SCHOLASTIC RECORD')
            || ! $this->cellContains($backSheet, 'B8', 'LEARNING AREAS')
        ) {
            return null;
        }

        $helperSheet = $this->firstMatchingSheet($spreadsheet, fn ($sheet): bool => (
            $this->normalizeText($sheet->getTitle()) === $this->normalizeText('Sir Wedz Helper Table')
            || (
                $this->cellContains($sheet, 'A1', 'AO')
                && $this->cellContains($sheet, 'C1', 'PASSED')
                && $this->cellContains($sheet, 'E1', 'PROMOTED')
            )
        ));

        return [
            'document_type' => DepedWorkbookDocumentType::Sf10EsTemplate->value,
            'document_label' => DepedWorkbookDocumentType::Sf10EsTemplate->label(),
            'handling_mode' => DepedWorkbookDocumentType::Sf10EsTemplate->handlingMode(),
            'template_document_type_hint' => DepedWorkbookDocumentType::Sf10EsTemplate->templateDocumentTypeHint(),
            ...$this->workbookSummary($spreadsheet),
            'source_sheet_name' => $frontSheet->getTitle(),
            'anchors' => [
                'front' => [
                    'B1' => $this->cellDisplayValue($frontSheet, 'B1'),
                    'H5' => $this->cellDisplayValue($frontSheet, 'H5'),
                    'B21' => $this->cellDisplayValue($frontSheet, 'B21'),
                    'B28' => $this->cellDisplayValue($frontSheet, 'B28'),
                ],
                'back' => [
                    'B1' => $this->cellDisplayValue($backSheet, 'B1'),
                    'B2' => $this->cellDisplayValue($backSheet, 'B2'),
                    'B8' => $this->cellDisplayValue($backSheet, 'B8'),
                    'B27' => $this->cellDisplayValue($backSheet, 'B27'),
                ],
                'helper' => $helperSheet === null
                    ? []
                    : [
                        'title' => $helperSheet->getTitle(),
                        'A1' => $this->cellDisplayValue($helperSheet, 'A1'),
                        'C1' => $this->cellDisplayValue($helperSheet, 'C1'),
                        'E1' => $this->cellDisplayValue($helperSheet, 'E1'),
                    ],
            ],
            'structural_readiness' => [
                'is_positional_template' => true,
                'ready_for_mapping' => true,
                'has_front_sheet' => true,
                'has_back_sheet' => true,
                'has_helper_sheet' => $helperSheet !== null,
            ],
            'rows' => [],
        ];
    }
}
