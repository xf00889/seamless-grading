<?php

namespace App\Enums;

enum TemplateMappingKind: string
{
    case FixedCell = 'fixed_cell';
    case NamedRange = 'named_range';
    case MergedCellTarget = 'merged_cell_target';
    case SplitFieldGroup = 'split_field_group';
    case RepeatingRowBlock = 'repeating_row_block';
    case SubjectTableBlock = 'subject_table_block';
    case SheetAnchorBased = 'sheet_anchor_based';

    public function label(): string
    {
        return match ($this) {
            self::FixedCell => 'Fixed Cell',
            self::NamedRange => 'Named Range',
            self::MergedCellTarget => 'Merged Cell Target',
            self::SplitFieldGroup => 'Split Field Group',
            self::RepeatingRowBlock => 'Repeating Row Block',
            self::SubjectTableBlock => 'Subject Table Block',
            self::SheetAnchorBased => 'Sheet Anchor Based',
        };
    }
}
