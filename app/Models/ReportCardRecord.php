<?php

namespace App\Models;

use App\Enums\TemplateDocumentType;
use Database\Factories\ReportCardRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportCardRecord extends Model
{
    use HasFactory;

    /** @use HasFactory<ReportCardRecordFactory> */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document_type' => TemplateDocumentType::class,
            'payload' => 'array',
            'generated_at' => 'datetime',
            'is_finalized' => 'boolean',
            'finalized_at' => 'datetime',
        ];
    }

    public function sectionRoster(): BelongsTo
    {
        return $this->belongsTo(SectionRoster::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function gradingPeriod(): BelongsTo
    {
        return $this->belongsTo(GradingPeriod::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ReportCardRecordAuditLog::class);
    }
}
