<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    /** @use HasFactory<UserFactory> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function advisorySections(): HasMany
    {
        return $this->hasMany(Section::class, 'adviser_id');
    }

    public function teacherLoads(): HasMany
    {
        return $this->hasMany(TeacherLoad::class, 'teacher_id');
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'imported_by');
    }

    public function confirmedImportBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class, 'confirmed_by');
    }

    public function submittedGradeSubmissions(): HasMany
    {
        return $this->hasMany(GradeSubmission::class, 'submitted_by');
    }

    public function gradeChangeLogs(): HasMany
    {
        return $this->hasMany(GradeChangeLog::class, 'changed_by');
    }

    public function gradingSheetExports(): HasMany
    {
        return $this->hasMany(GradingSheetExport::class, 'exported_by');
    }

    public function gradingSheetExportAuditLogs(): HasMany
    {
        return $this->hasMany(GradingSheetExportAuditLog::class, 'acted_by');
    }

    public function reportCardRecords(): HasMany
    {
        return $this->hasMany(ReportCardRecord::class, 'generated_by');
    }

    public function finalizedReportCardRecords(): HasMany
    {
        return $this->hasMany(ReportCardRecord::class, 'finalized_by');
    }

    public function reportCardRecordAuditLogs(): HasMany
    {
        return $this->hasMany(ReportCardRecordAuditLog::class, 'acted_by');
    }

    public function learnerStatusAuditLogs(): HasMany
    {
        return $this->hasMany(LearnerStatusAuditLog::class, 'acted_by');
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ApprovalLog::class, 'acted_by');
    }
}
