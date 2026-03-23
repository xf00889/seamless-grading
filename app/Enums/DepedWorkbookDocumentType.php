<?php

namespace App\Enums;

enum DepedWorkbookDocumentType: string
{
    case Sf1ShsRosterImport = 'sf1_shs_roster_import';
    case Sf10EsTemplate = 'sf10_es_template';
    case CardTemplate = 'card_template';

    public function label(): string
    {
        return match ($this) {
            self::Sf1ShsRosterImport => 'DepEd SF1-SHS roster import',
            self::Sf10EsTemplate => 'DepEd SF10-ES template',
            self::CardTemplate => 'DepEd CARD template',
        };
    }

    public function handlingMode(): string
    {
        return match ($this) {
            self::Sf1ShsRosterImport => 'learner_import',
            self::Sf10EsTemplate, self::CardTemplate => 'template_inspection',
        };
    }

    public function templateDocumentTypeHint(): ?string
    {
        return match ($this) {
            self::Sf1ShsRosterImport => null,
            self::Sf10EsTemplate => TemplateDocumentType::Sf10->value,
            self::CardTemplate => TemplateDocumentType::Sf9->value,
        };
    }

    public function sf1ImportRejectionMessage(): string
    {
        return match ($this) {
            self::Sf10EsTemplate => 'Detected a DepEd SF10-ES template workbook. SF10 templates are not learner roster import files; upload this workbook through template management instead.',
            self::CardTemplate => 'Detected a DepEd CARD template workbook. CARD templates are not learner roster import files; upload this workbook through template management instead.',
            self::Sf1ShsRosterImport => 'Detected workbook type is already a supported SF1-SHS roster import.',
        };
    }
}
