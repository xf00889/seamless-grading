<?php

namespace App\Services\DepedWorkbook;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class DepedWorkbookInspectionService
{
    public function __construct(
        private readonly DepedSf1ShsParser $sf1Parser,
        private readonly DepedSf10EsTemplateInspector $sf10Inspector,
        private readonly DepedCardTemplateInspector $cardInspector,
    ) {}

    public function inspectUploadedFile(UploadedFile $file): array
    {
        return $this->inspectPath($file->getRealPath());
    }

    public function inspectPath(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable) {
            throw UnsupportedDepedWorkbookException::unreadable();
        }

        return $this->inspectSpreadsheet($spreadsheet);
    }

    public function inspectSpreadsheet(Spreadsheet $spreadsheet): array
    {
        foreach ($this->inspectors() as $inspector) {
            $inspection = $inspector->inspect($spreadsheet);

            if ($inspection !== null) {
                return $inspection;
            }
        }

        throw UnsupportedDepedWorkbookException::unsupportedStructure();
    }

    /**
     * @return array<int, DepedWorkbookInspector>
     */
    private function inspectors(): array
    {
        return [
            $this->sf1Parser,
            $this->sf10Inspector,
            $this->cardInspector,
        ];
    }
}
