<?php

namespace App\Services\TemplateManagement;

use App\Enums\TemplateMappingKind;
use App\Models\Template;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TemplateMappingStatusEvaluator
{
    public function __construct(
        private readonly TemplateDefinitionRegistry $definitionRegistry,
        private readonly TemplateWorkbookInspectionService $inspectionService,
        private readonly TemplateWorksheetSuggestionService $worksheetSuggestionService,
    ) {}

    public function evaluate(Template $template): array
    {
        $template->loadMissing('fieldMaps');

        $definitions = collect($this->definitionRegistry->definitionsFor($template->document_type));
        $fieldMaps = $template->fieldMaps->keyBy('field_key');
        $inspection = $this->inspectionService->inspectTemplate($template);
        $sheetSuggestions = $this->worksheetSuggestionService->suggestionsFor($template->document_type, $inspection);
        $profile = (string) ($inspection['profile'] ?? 'generic');
        $spreadsheet = $this->loadSpreadsheet($template);
        $targetUsages = [];
        $errors = [];
        $errorKeys = [];
        $missingRequiredFields = [];
        $invalidFields = [];

        $rows = $definitions
            ->map(function (array $definition) use (
                $fieldMaps,
                $inspection,
                $sheetSuggestions,
                $profile,
                $spreadsheet,
                &$targetUsages,
                &$errors,
                &$errorKeys,
                &$missingRequiredFields,
                &$invalidFields,
            ): array {
                $fieldMap = $fieldMaps->get($definition['field_key']);
                $mappingKind = $fieldMap?->mapping_kind ?? $this->defaultMappingKind($definition);
                $sheetName = $this->nullableString($fieldMap?->sheet_name);
                $suggestedSheetName = $sheetSuggestions[$definition['field_key']] ?? null;
                $effectiveSheetName = $sheetName ?? $suggestedSheetName;
                $targetCell = $this->nullableString($fieldMap?->target_cell);
                $defaultValue = $fieldMap?->default_value;
                $mappingConfig = is_array($fieldMap?->mapping_config) ? $fieldMap->mapping_config : [];
                $allowedMappingKinds = $this->allowedMappingKinds($definition);
                $isRequired = $this->isRequiredForProfile($definition, $profile);

                $validation = $this->validateMapping(
                    definition: $definition,
                    mappingKind: $mappingKind,
                    sheetName: $effectiveSheetName,
                    targetCell: $targetCell,
                    mappingConfig: $mappingConfig,
                    inspection: $inspection,
                    spreadsheet: $spreadsheet,
                    allowedMappingKinds: $allowedMappingKinds,
                    profile: $profile,
                    isRequired: $isRequired,
                );

                if ($validation['duplicate_target_key'] !== null) {
                    $targetUsages[$validation['duplicate_target_key']][] = $definition['field_key'];
                }

                if ($validation['error'] !== null) {
                    $errors[$definition['field_key']] = $validation['error'];
                    $errorKeys[$definition['field_key']] = $validation['attribute'];
                    $invalidFields[] = $definition['field_key'];

                    if ($isRequired && ! $validation['is_mapped']) {
                        $missingRequiredFields[] = $definition['field_key'];
                    }
                }

                return [
                    'field_key' => $definition['field_key'],
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'is_required' => $isRequired,
                    'target_cell' => $targetCell,
                    'default_value' => $defaultValue,
                    'is_valid' => $validation['error'] === null,
                    'is_mapped' => $validation['is_mapped'],
                    'error' => $validation['error'],
                    'validation_attribute' => $validation['attribute'],
                    'mapping_kind' => $mappingKind->value,
                    'mapping_kind_label' => $mappingKind->label(),
                    'allowed_mapping_kinds' => array_map(
                        fn (TemplateMappingKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
                        $allowedMappingKinds,
                    ),
                    'sheet_name' => $sheetName,
                    'suggested_sheet_name' => $suggestedSheetName,
                    'effective_sheet_name' => $effectiveSheetName,
                    'uses_suggested_sheet' => $sheetName === null && $suggestedSheetName !== null,
                    'mapping_config' => $mappingConfig,
                    'mapping_config_json' => $this->mappingConfigJson($mappingConfig),
                    'target_summary' => $validation['target_summary'],
                ];
            })
            ->values();

        foreach ($targetUsages as $targetKey => $fieldKeys) {
            if (count($fieldKeys) < 2) {
                continue;
            }

            foreach ($fieldKeys as $fieldKey) {
                $errors[$fieldKey] = 'This worksheet target is already used by another field in the same template version.';
                $errorKeys[$fieldKey] ??= "mappings.$fieldKey.target_cell";
                $invalidFields[] = $fieldKey;
            }
        }

        $rows = $rows->map(function (array $row) use ($errors, $errorKeys): array {
            if (isset($errors[$row['field_key']])) {
                $row['is_valid'] = false;
                $row['error'] = $errors[$row['field_key']];
                $row['validation_attribute'] = $errorKeys[$row['field_key']] ?? $row['validation_attribute'];
            }

            return $row;
        });

        $requiredRows = $rows->where('is_required', true);
        $validRequiredRows = $requiredRows->filter(
            fn (array $row): bool => $row['is_valid'] && $row['is_mapped'],
        );

        $issues = collect($inspection['issues'] ?? [])
            ->merge(collect($errors)->map(
                fn (string $message, string $fieldKey): string => $this->definitionRegistry
                    ->fieldDefinition($template->document_type, $fieldKey)['label'].': '.$message
            ))
            ->values()
            ->all();

        $status = match (true) {
            ($inspection['ready_for_mapping'] ?? true) !== true => ['value' => 'broken', 'label' => 'Broken', 'tone' => 'rose'],
            $invalidFields !== [] => ['value' => 'broken', 'label' => 'Broken', 'tone' => 'rose'],
            $missingRequiredFields !== [] => ['value' => 'incomplete', 'label' => 'Incomplete', 'tone' => 'amber'],
            default => ['value' => 'complete', 'label' => 'Complete', 'tone' => 'emerald'],
        };

        return [
            'status' => $status,
            'required_total' => $requiredRows->count(),
            'required_valid' => $validRequiredRows->count(),
            'mapped_total' => $rows->filter(fn (array $row): bool => $row['is_mapped'])->count(),
            'completion_percentage' => $requiredRows->count() > 0
                ? (int) round(($validRequiredRows->count() / $requiredRows->count()) * 100)
                : 100,
            'rows' => $rows->all(),
            'errors' => $errors,
            'error_keys' => $errorKeys,
            'issues' => $issues,
            'missing_required_fields' => array_values(array_unique($missingRequiredFields)),
            'invalid_fields' => array_values(array_unique($invalidFields)),
            'workbook_inspection' => $inspection,
        ];
    }

    public function defaultMappingKind(array $definition): TemplateMappingKind
    {
        $value = $definition['default_mapping_kind'] ?? TemplateMappingKind::FixedCell->value;

        return TemplateMappingKind::from($value);
    }

    /**
     * @return array<int, TemplateMappingKind>
     */
    public function allowedMappingKinds(array $definition): array
    {
        return collect($definition['allowed_mapping_kinds'] ?? [TemplateMappingKind::FixedCell->value])
            ->map(fn (string $value): TemplateMappingKind => TemplateMappingKind::from($value))
            ->all();
    }

    public function isValidTargetCell(?string $targetCell): bool
    {
        if ($targetCell === null) {
            return false;
        }

        $normalizedTarget = trim($targetCell);

        return $this->isValidCellReference($normalizedTarget) || $this->isValidNamedRange($normalizedTarget);
    }

    private function validateMapping(
        array $definition,
        TemplateMappingKind $mappingKind,
        ?string $sheetName,
        ?string $targetCell,
        array $mappingConfig,
        array $inspection,
        ?Spreadsheet $spreadsheet,
        array $allowedMappingKinds,
        string $profile,
        bool $isRequired,
    ): array {
        $fieldKey = (string) $definition['field_key'];
        $allowedSheetNames = $this->allowedSheetNames($fieldKey, $profile, $inspection);
        $hasMappingPayload = $this->hasMappingPayload($mappingKind, $targetCell, $mappingConfig);

        if (! in_array($mappingKind, $allowedMappingKinds, true)) {
            return $this->errorPayload(
                'Choose a supported mapping type for this template field.',
                "mappings.$fieldKey.mapping_kind",
                $hasMappingPayload,
            );
        }

        if (! $isRequired && ! $hasMappingPayload) {
            return $this->okPayload('Not set', false);
        }

        if (($inspection['ready_for_mapping'] ?? true) !== true) {
            return $this->errorPayload(
                'The workbook structure must be recognized before this field can be validated.',
                "mappings.$fieldKey.mapping_kind",
                $hasMappingPayload,
            );
        }

        $sheetRequired = $profile !== 'generic' || in_array($mappingKind, [
            TemplateMappingKind::MergedCellTarget,
            TemplateMappingKind::SplitFieldGroup,
            TemplateMappingKind::RepeatingRowBlock,
            TemplateMappingKind::SubjectTableBlock,
            TemplateMappingKind::SheetAnchorBased,
        ], true);

        if ($sheetRequired && $sheetName === null) {
            return $this->errorPayload(
                'Select the worksheet that owns this mapping.',
                "mappings.$fieldKey.sheet_name",
                $hasMappingPayload,
            );
        }

        if ($sheetName !== null && ! in_array($sheetName, $inspection['sheet_names'] ?? [], true)) {
            return $this->errorPayload(
                'The selected worksheet does not exist in the uploaded workbook.',
                "mappings.$fieldKey.sheet_name",
                true,
            );
        }

        if ($sheetName !== null && $allowedSheetNames !== null && ! in_array($sheetName, $allowedSheetNames, true)) {
            return $this->errorPayload(
                'This mapping must target one of the expected worksheet areas for this template type.',
                "mappings.$fieldKey.sheet_name",
                true,
            );
        }

        return match ($mappingKind) {
            TemplateMappingKind::FixedCell,
            TemplateMappingKind::NamedRange => $this->validateScalarTarget($fieldKey, $targetCell, $sheetName),
            TemplateMappingKind::MergedCellTarget => $this->validateMergedCellTarget(
                fieldKey: $fieldKey,
                targetCell: $targetCell,
                sheetName: $sheetName,
                inspection: $inspection,
            ),
            TemplateMappingKind::SplitFieldGroup => $this->validateSplitFieldGroup(
                fieldKey: $fieldKey,
                mappingConfig: $mappingConfig,
                sheetName: $sheetName,
                profile: $profile,
                spreadsheet: $spreadsheet,
            ),
            TemplateMappingKind::RepeatingRowBlock => $this->validateAnchorBlockMapping(
                fieldKey: $fieldKey,
                targetCell: $targetCell,
                sheetName: $sheetName,
                mappingConfig: $mappingConfig,
                spreadsheet: $spreadsheet,
                blockLabel: 'repeating record block',
            ),
            TemplateMappingKind::SubjectTableBlock => $this->validateAnchorBlockMapping(
                fieldKey: $fieldKey,
                targetCell: $targetCell,
                sheetName: $sheetName,
                mappingConfig: $mappingConfig,
                spreadsheet: $spreadsheet,
                blockLabel: 'subject table block',
            ),
            TemplateMappingKind::SheetAnchorBased => $this->validateSheetAnchorBased(
                fieldKey: $fieldKey,
                targetCell: $targetCell,
                sheetName: $sheetName,
                mappingConfig: $mappingConfig,
                spreadsheet: $spreadsheet,
            ),
        };
    }

    private function validateScalarTarget(string $fieldKey, ?string $targetCell, ?string $sheetName): array
    {
        if ($targetCell === null) {
            return $this->errorPayload(
                'A worksheet target is required before this template can be activated.',
                "mappings.$fieldKey.target_cell",
                false,
            );
        }

        if (! $this->isValidTargetCell($targetCell)) {
            return $this->errorPayload(
                'Use a valid spreadsheet cell reference or named range.',
                "mappings.$fieldKey.target_cell",
                true,
            );
        }

        $duplicateKey = $this->duplicateTargetKey($sheetName, $targetCell);

        return $this->okPayload(
            $sheetName === null ? $targetCell : $sheetName.'!'.$targetCell,
            true,
            $duplicateKey,
        );
    }

    private function validateMergedCellTarget(
        string $fieldKey,
        ?string $targetCell,
        ?string $sheetName,
        array $inspection,
    ): array {
        if ($targetCell === null) {
            return $this->errorPayload(
                'A merged-cell target requires a target cell inside the merged worksheet area.',
                "mappings.$fieldKey.target_cell",
                false,
            );
        }

        if (! $this->isValidCellReference($targetCell)) {
            return $this->errorPayload(
                'Use a valid worksheet cell reference for a merged-cell target.',
                "mappings.$fieldKey.target_cell",
                true,
            );
        }

        $mergeRanges = collect($inspection['sheet_summaries'] ?? [])
            ->firstWhere('name', $sheetName)['merge_ranges'] ?? [];

        $matchesMergedRange = collect($mergeRanges)->contains(
            fn (string $range): bool => $this->cellFallsInsideRange($targetCell, $range),
        );

        if (! $matchesMergedRange) {
            return $this->errorPayload(
                'The selected target cell is not inside a merged worksheet area.',
                "mappings.$fieldKey.target_cell",
                true,
            );
        }

        return $this->okPayload($sheetName.'!'.$targetCell, true, $this->duplicateTargetKey($sheetName, $targetCell));
    }

    private function validateSplitFieldGroup(
        string $fieldKey,
        array $mappingConfig,
        ?string $sheetName,
        string $profile,
        ?Spreadsheet $spreadsheet,
    ): array {
        $parts = Arr::get($mappingConfig, 'parts', []);

        if (! is_array($parts) || $parts === []) {
            return $this->errorPayload(
                'A split field group requires a JSON config with a "parts" object.',
                "mappings.$fieldKey.mapping_config_json",
                false,
            );
        }

        $requiredParts = $profile === 'deped_card' || $profile === 'deped_sf10'
            ? ['last_name', 'first_name']
            : ['full_name'];

        foreach ($requiredParts as $partKey) {
            $cell = $this->nullableString($parts[$partKey] ?? null);

            if ($cell === null) {
                return $this->errorPayload(
                    'The split field group is incomplete. Missing required part: '.$partKey.'.',
                    "mappings.$fieldKey.mapping_config_json",
                    true,
                );
            }

            if (! $this->isValidCellReference($cell)) {
                return $this->errorPayload(
                    'Every split field group part must use a valid worksheet cell reference.',
                    "mappings.$fieldKey.mapping_config_json",
                    true,
                );
            }
        }

        if ($spreadsheet !== null && ! $this->sheetAnchorsSatisfied($spreadsheet, $sheetName, $profile, $fieldKey)) {
            return $this->errorPayload(
                'The selected worksheet does not match the expected learner-identity anchors for this template type.',
                "mappings.$fieldKey.sheet_name",
                true,
            );
        }

        return $this->okPayload(
            $sheetName.' · '.collect($parts)
                ->filter(fn ($value): bool => $this->nullableString($value) !== null)
                ->map(fn ($value, $key): string => $key.':'.$value)
                ->implode(', '),
            true,
        );
    }

    private function validateAnchorBlockMapping(
        string $fieldKey,
        ?string $targetCell,
        ?string $sheetName,
        array $mappingConfig,
        ?Spreadsheet $spreadsheet,
        string $blockLabel,
    ): array {
        if ($targetCell === null || ! $this->isValidCellReference($targetCell)) {
            return $this->errorPayload(
                'A '.$blockLabel.' requires a valid start cell.',
                "mappings.$fieldKey.target_cell",
                false,
            );
        }

        $anchorCell = $this->nullableString(Arr::get($mappingConfig, 'anchor_cell'));
        $anchorText = $this->nullableString(Arr::get($mappingConfig, 'anchor_text'));

        if ($anchorCell === null || $anchorText === null) {
            return $this->errorPayload(
                'A '.$blockLabel.' requires both anchor_cell and anchor_text in the mapping config.',
                "mappings.$fieldKey.mapping_config_json",
                true,
            );
        }

        if (! $this->isValidCellReference($anchorCell)) {
            return $this->errorPayload(
                'Use a valid worksheet cell reference for the anchor cell.',
                "mappings.$fieldKey.mapping_config_json",
                true,
            );
        }

        if ($spreadsheet !== null && ! $this->sheetAnchorMatches($spreadsheet, $sheetName, $anchorCell, $anchorText)) {
            return $this->errorPayload(
                'The worksheet anchor for this block mapping does not match the uploaded workbook.',
                "mappings.$fieldKey.mapping_config_json",
                true,
            );
        }

        return $this->okPayload(
            sprintf('%s!%s (anchor %s)', $sheetName, $targetCell, $anchorCell),
            true,
        );
    }

    private function validateSheetAnchorBased(
        string $fieldKey,
        ?string $targetCell,
        ?string $sheetName,
        array $mappingConfig,
        ?Spreadsheet $spreadsheet,
    ): array {
        if ($targetCell === null || ! $this->isValidCellReference($targetCell)) {
            return $this->errorPayload(
                'An anchor-based mapping requires a valid target cell.',
                "mappings.$fieldKey.target_cell",
                false,
            );
        }

        $anchorCell = $this->nullableString(Arr::get($mappingConfig, 'anchor_cell'));
        $anchorText = $this->nullableString(Arr::get($mappingConfig, 'anchor_text'));

        if ($anchorCell === null || $anchorText === null) {
            return $this->errorPayload(
                'An anchor-based mapping requires both anchor_cell and anchor_text in the mapping config.',
                "mappings.$fieldKey.mapping_config_json",
                true,
            );
        }

        if (! $this->isValidCellReference($anchorCell)) {
            return $this->errorPayload(
                'Use a valid worksheet cell reference for the anchor cell.',
                "mappings.$fieldKey.mapping_config_json",
                true,
            );
        }

        if ($spreadsheet !== null && ! $this->sheetAnchorMatches($spreadsheet, $sheetName, $anchorCell, $anchorText)) {
            return $this->errorPayload(
                'The worksheet anchor for this mapping does not match the uploaded workbook.',
                "mappings.$fieldKey.mapping_config_json",
                true,
            );
        }

        return $this->okPayload(
            sprintf('%s!%s (anchor %s)', $sheetName, $targetCell, $anchorCell),
            true,
            $this->duplicateTargetKey($sheetName, $targetCell),
        );
    }

    private function errorPayload(string $message, string $attribute, bool $isMapped): array
    {
        return [
            'error' => $message,
            'attribute' => $attribute,
            'is_mapped' => $isMapped,
            'target_summary' => 'Not set',
            'duplicate_target_key' => null,
        ];
    }

    private function okPayload(string $targetSummary, bool $isMapped, ?string $duplicateTargetKey = null): array
    {
        return [
            'error' => null,
            'attribute' => null,
            'is_mapped' => $isMapped,
            'target_summary' => $targetSummary,
            'duplicate_target_key' => $duplicateTargetKey,
        ];
    }

    private function isRequiredForProfile(array $definition, string $profile): bool
    {
        $fieldKey = (string) $definition['field_key'];

        if ($profile === 'deped_card' && in_array($fieldKey, [
            'grading_period_label',
            'subject_remarks_column',
            'promotion_remarks',
        ], true)) {
            return false;
        }

        if ($profile === 'deped_sf10' && in_array($fieldKey, [
            'learner_status',
            'general_average',
        ], true)) {
            return false;
        }

        return (bool) ($definition['required'] ?? false);
    }

    private function allowedSheetNames(string $fieldKey, string $profile, array $inspection): ?array
    {
        return match ($profile) {
            'deped_card' => match (true) {
                in_array($fieldKey, [
                    'school_name',
                    'school_year_name',
                    'grading_period_label',
                    'grade_level_name',
                    'section_name',
                    'learner_name',
                    'learner_lrn',
                    'adviser_name',
                ], true) => [$this->roleSheetName($inspection, 'front') ?? 'CARD BLANK FRONT'],
                in_array($fieldKey, [
                    'subject_name_column',
                    'subject_grade_column',
                    'subject_remarks_column',
                    'general_average',
                    'promotion_remarks',
                ], true) => [$this->roleSheetName($inspection, 'inside') ?? 'CARD BLANK INSIDE'],
                default => null,
            },
            'deped_sf10' => match (true) {
                in_array($fieldKey, ['learner_name', 'learner_lrn'], true) => ['Front'],
                in_array($fieldKey, [
                    'school_name',
                    'grade_level_name',
                    'school_year_name',
                    'section_name',
                    'adviser_name',
                    'learner_status',
                    'general_average',
                    'subject_name_column',
                    'final_rating_column',
                    'action_taken_column',
                ], true) => ['Front', 'Back'],
                default => null,
            },
            default => null,
        };
    }

    private function roleSheetName(array $inspection, string $role): ?string
    {
        $sheetRoles = $inspection['sheet_roles'] ?? [];

        if (! is_array($sheetRoles)) {
            return null;
        }

        $sheetName = $this->nullableString($sheetRoles[$role] ?? null);

        if ($sheetName === null || ! in_array($sheetName, $inspection['sheet_names'] ?? [], true)) {
            return null;
        }

        return $sheetName;
    }

    private function duplicateTargetKey(?string $sheetName, string $targetCell): string
    {
        return Str::upper(($sheetName ?? '_default').'!'.$targetCell);
    }

    private function hasMappingPayload(TemplateMappingKind $mappingKind, ?string $targetCell, array $mappingConfig): bool
    {
        return match ($mappingKind) {
            TemplateMappingKind::SplitFieldGroup => Arr::get($mappingConfig, 'parts') !== null,
            default => $targetCell !== null || $mappingConfig !== [],
        };
    }

    private function mappingConfigJson(array $mappingConfig): ?string
    {
        if ($mappingConfig === []) {
            return null;
        }

        return json_encode($mappingConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = trim((string) ($value ?? ''));

        return $stringValue !== '' ? $stringValue : null;
    }

    private function isValidCellReference(string $value): bool
    {
        return preg_match('/^[A-Z]{1,3}[1-9][0-9]{0,6}$/i', $value) === 1;
    }

    private function isValidNamedRange(string $value): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $value) === 1;
    }

    private function cellFallsInsideRange(string $cell, string $range): bool
    {
        [$start, $end] = explode(':', strtoupper($range));
        [$startColumn, $startRow] = Coordinate::indexesFromString($start);
        [$endColumn, $endRow] = Coordinate::indexesFromString($end);
        [$column, $row] = Coordinate::indexesFromString(strtoupper($cell));

        return $column >= $startColumn
            && $column <= $endColumn
            && $row >= $startRow
            && $row <= $endRow;
    }

    private function loadSpreadsheet(Template $template): ?Spreadsheet
    {
        if ($template->file_path === null || $template->file_path === '') {
            return null;
        }

        try {
            $path = Storage::disk($template->file_disk)->path($template->file_path);
        } catch (\Throwable) {
            return null;
        }

        if (! is_file($path)) {
            return null;
        }

        try {
            return IOFactory::load($path);
        } catch (\Throwable) {
            return null;
        }
    }

    private function sheetAnchorMatches(Spreadsheet $spreadsheet, ?string $sheetName, string $anchorCell, string $anchorText): bool
    {
        if ($sheetName === null) {
            return false;
        }

        $worksheet = $spreadsheet->getSheetByName($sheetName);

        if (! $worksheet instanceof Worksheet) {
            return false;
        }

        return str_contains(
            Str::upper(Str::squish((string) $worksheet->getCell($anchorCell)->getFormattedValue())),
            Str::upper(Str::squish($anchorText)),
        );
    }

    private function sheetAnchorsSatisfied(Spreadsheet $spreadsheet, ?string $sheetName, string $profile, string $fieldKey): bool
    {
        if ($fieldKey !== 'learner_name' || $sheetName === null) {
            return true;
        }

        return match ($profile) {
            'deped_card' => $this->sheetAnchorMatches($spreadsheet, $sheetName, 'U12', 'Name:')
                && $this->sheetAnchorMatches($spreadsheet, $sheetName, 'W13', 'Last Name'),
            'deped_sf10' => $this->sheetAnchorMatches($spreadsheet, $sheetName, 'B9', 'LAST NAME:')
                && $this->sheetAnchorMatches($spreadsheet, $sheetName, 'N9', 'FIRST NAME:'),
            default => true,
        };
    }
}
