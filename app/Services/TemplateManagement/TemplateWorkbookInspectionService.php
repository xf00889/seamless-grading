<?php

namespace App\Services\TemplateManagement;

use App\Enums\DepedWorkbookDocumentType;
use App\Enums\TemplateDocumentType;
use App\Models\Template;
use App\Services\DepedWorkbook\DepedWorkbookInspectionService;
use App\Services\DepedWorkbook\UnsupportedDepedWorkbookException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class TemplateWorkbookInspectionService
{
    public function __construct(
        private readonly DepedWorkbookInspectionService $depedInspectionService,
    ) {}

    public function inspectTemplate(Template $template): array
    {
        if ($template->file_path === null || $template->file_path === '') {
            return $this->missingFileInspection($template->document_type);
        }

        $path = Storage::disk($template->file_disk)->path($template->file_path);

        return $this->inspectPath($path, $template->document_type);
    }

    public function inspectUploadedFile(UploadedFile $file, TemplateDocumentType $documentType): array
    {
        return $this->inspectPath($file->getRealPath(), $documentType);
    }

    public function inspectPath(string $path, TemplateDocumentType $documentType): array
    {
        if (! is_file($path)) {
            return $this->missingFileInspection($documentType);
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable) {
            return [
                'status' => 'unreadable',
                'profile' => 'unsupported',
                'document_type' => $documentType->value,
                'document_label' => $documentType->label(),
                'workbook_label' => 'Unreadable workbook',
                'sheet_count' => 0,
                'sheet_names' => [],
                'sheet_roles' => [],
                'sheet_summaries' => [],
                'anchors' => [],
                'is_positional_template' => false,
                'ready_for_mapping' => false,
                'issues' => ['The stored workbook could not be opened for template inspection.'],
            ];
        }

        $genericInspection = $this->genericInspection($spreadsheet, $documentType);

        if ($documentType === TemplateDocumentType::GradingSheet) {
            return $genericInspection;
        }

        try {
            $depedInspection = $this->depedInspectionService->inspectSpreadsheet($spreadsheet);
        } catch (UnsupportedDepedWorkbookException) {
            return $genericInspection;
        }

        $detectedDocumentType = $depedInspection['document_type'] ?? null;
        $detectedHint = $depedInspection['template_document_type_hint'] ?? null;

        if ($detectedHint !== $documentType->value) {
            return [
                ...$genericInspection,
                'status' => 'mismatch',
                'profile' => 'mismatch',
                'detected_document_type' => $detectedDocumentType,
                'detected_document_label' => $depedInspection['document_label'] ?? null,
                'is_positional_template' => (bool) ($depedInspection['structural_readiness']['is_positional_template'] ?? false),
                'ready_for_mapping' => false,
                'sheet_roles' => $depedInspection['sheet_roles'] ?? [],
                'anchors' => $depedInspection['anchors'] ?? [],
                'issues' => [
                    'The uploaded workbook is structurally recognized as '.$this->detectedLabel($detectedDocumentType)
                    .' and does not match the selected '.$documentType->label().' template type.',
                ],
            ];
        }

        $profile = match ($detectedDocumentType) {
            DepedWorkbookDocumentType::CardTemplate->value => 'deped_card',
            DepedWorkbookDocumentType::Sf10EsTemplate->value => 'deped_sf10',
            default => 'generic',
        };

        return [
            ...$genericInspection,
            'status' => 'recognized',
            'profile' => $profile,
            'detected_document_type' => $detectedDocumentType,
            'detected_document_label' => $depedInspection['document_label'] ?? null,
            'is_positional_template' => (bool) ($depedInspection['structural_readiness']['is_positional_template'] ?? false),
            'ready_for_mapping' => (bool) ($depedInspection['structural_readiness']['ready_for_mapping'] ?? false),
            'sheet_roles' => $depedInspection['sheet_roles'] ?? [],
            'anchors' => $depedInspection['anchors'] ?? [],
            'issues' => [],
        ];
    }

    private function genericInspection(Spreadsheet $spreadsheet, TemplateDocumentType $documentType): array
    {
        return [
            'status' => 'generic',
            'profile' => 'generic',
            'document_type' => $documentType->value,
            'document_label' => $documentType->label(),
            'workbook_label' => 'Generic spreadsheet workbook',
            'sheet_count' => count($spreadsheet->getSheetNames()),
            'sheet_names' => $spreadsheet->getSheetNames(),
            'sheet_roles' => [],
            'sheet_summaries' => collect($spreadsheet->getWorksheetIterator())
                ->map(fn ($sheet): array => [
                    'name' => $sheet->getTitle(),
                    'merged_cell_count' => count($sheet->getMergeCells()),
                    'merge_ranges' => array_values($sheet->getMergeCells()),
                ])
                ->values()
                ->all(),
            'anchors' => [],
            'is_positional_template' => false,
            'ready_for_mapping' => true,
            'issues' => [],
        ];
    }

    private function missingFileInspection(TemplateDocumentType $documentType): array
    {
        return [
            'status' => 'missing',
            'profile' => 'unsupported',
            'document_type' => $documentType->value,
            'document_label' => $documentType->label(),
            'workbook_label' => 'Missing workbook file',
            'sheet_count' => 0,
            'sheet_names' => [],
            'sheet_roles' => [],
            'sheet_summaries' => [],
            'anchors' => [],
            'is_positional_template' => false,
            'ready_for_mapping' => false,
            'issues' => ['The stored workbook file is missing and cannot be inspected.'],
        ];
    }

    private function detectedLabel(?string $detectedDocumentType): string
    {
        return match ($detectedDocumentType) {
            DepedWorkbookDocumentType::CardTemplate->value => DepedWorkbookDocumentType::CardTemplate->label(),
            DepedWorkbookDocumentType::Sf10EsTemplate->value => DepedWorkbookDocumentType::Sf10EsTemplate->label(),
            DepedWorkbookDocumentType::Sf1ShsRosterImport->value => DepedWorkbookDocumentType::Sf1ShsRosterImport->label(),
            default => 'an unsupported workbook type',
        };
    }
}
