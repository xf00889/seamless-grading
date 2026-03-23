<?php

namespace App\Services\Sf1Import;

use App\Enums\DepedWorkbookDocumentType;
use App\Services\DepedWorkbook\DepedWorkbookInspectionService;
use App\Services\DepedWorkbook\UnsupportedDepedWorkbookException;
use Illuminate\Http\UploadedFile;

class Sf1WorkbookParser
{
    public function __construct(
        private readonly DepedWorkbookInspectionService $inspectionService,
    ) {}

    public function parse(UploadedFile $file): array
    {
        $inspection = $this->inspectionService->inspectUploadedFile($file);

        if ($inspection['document_type'] !== DepedWorkbookDocumentType::Sf1ShsRosterImport->value) {
            throw UnsupportedDepedWorkbookException::unsupportedSf1Import(
                DepedWorkbookDocumentType::from($inspection['document_type']),
            );
        }

        return $inspection['rows'] ?? [];
    }
}
