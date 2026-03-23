<?php

namespace App\Models;

use App\Enums\TemplateMappingKind;
use Database\Factories\TemplateFieldMapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateFieldMap extends Model
{
    use HasFactory;

    /** @use HasFactory<TemplateFieldMapFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'mapping_kind' => TemplateMappingKind::class,
            'mapping_config' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
