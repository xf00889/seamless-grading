<?php

use App\Enums\GradingSheetExportAuditAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_sheet_export_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grading_sheet_export_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32)->default(GradingSheetExportAuditAction::Exported->value);
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['grading_sheet_export_id', 'action'], 'grading_sheet_export_audit_logs_export_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_sheet_export_audit_logs');
    }
};
