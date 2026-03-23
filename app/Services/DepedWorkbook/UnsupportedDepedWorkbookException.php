<?php

namespace App\Services\DepedWorkbook;

use App\Enums\DepedWorkbookDocumentType;
use RuntimeException;

class UnsupportedDepedWorkbookException extends RuntimeException
{
    public static function unreadable(): self
    {
        return new self('The uploaded workbook could not be opened. Please upload a valid Excel workbook and try again.');
    }

    public static function unsupportedStructure(): self
    {
        return new self('The uploaded workbook structure is not a supported DepEd SF1 roster import, SF10 template, or CARD template layout.');
    }

    public static function unsupportedSf1Import(DepedWorkbookDocumentType $documentType): self
    {
        return new self($documentType->sf1ImportRejectionMessage());
    }
}
