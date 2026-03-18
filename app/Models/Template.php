<?php

namespace App\Models;

use App\Enums\TemplateDocumentType;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory;

    /** @use HasFactory<TemplateFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_type' => TemplateDocumentType::class,
            'is_active' => 'boolean',
        ];
    }

    public function fieldMaps(): HasMany
    {
        return $this->hasMany(TemplateFieldMap::class);
    }

    public function gradingSheetExports(): HasMany
    {
        return $this->hasMany(GradingSheetExport::class);
    }

    public function reportCardRecords(): HasMany
    {
        return $this->hasMany(ReportCardRecord::class);
    }
}
