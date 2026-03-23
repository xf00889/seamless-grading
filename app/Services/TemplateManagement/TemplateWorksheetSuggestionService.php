<?php

namespace App\Services\TemplateManagement;

use App\Enums\TemplateDocumentType;

class TemplateWorksheetSuggestionService
{
    /**
     * @return array<string, string>
     */
    public function suggestionsFor(TemplateDocumentType $documentType, array $inspection): array
    {
        if ($documentType !== TemplateDocumentType::Sf9 || ($inspection['profile'] ?? null) !== 'deped_card') {
            return [];
        }

        $frontSheet = $this->roleSheetName($inspection, 'front');
        $insideSheet = $this->roleSheetName($inspection, 'inside');

        if ($frontSheet === null || $insideSheet === null) {
            return [];
        }

        return [
            'school_name' => $frontSheet,
            'school_year_name' => $frontSheet,
            'grade_level_name' => $frontSheet,
            'section_name' => $frontSheet,
            'learner_name' => $frontSheet,
            'learner_lrn' => $frontSheet,
            'adviser_name' => $frontSheet,
            'subject_name_column' => $insideSheet,
            'subject_grade_column' => $insideSheet,
            'general_average' => $insideSheet,
        ];
    }

    private function roleSheetName(array $inspection, string $role): ?string
    {
        $sheetRoles = $inspection['sheet_roles'] ?? [];

        if (! is_array($sheetRoles)) {
            return null;
        }

        $sheetName = trim((string) ($sheetRoles[$role] ?? ''));

        if ($sheetName === '' || ! in_array($sheetName, $inspection['sheet_names'] ?? [], true)) {
            return null;
        }

        return $sheetName;
    }
}
