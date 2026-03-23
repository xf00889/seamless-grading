<?php

use App\Enums\ReportCardRecordAuditAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_card_record_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_card_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32)->default(ReportCardRecordAuditAction::Exported->value);
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['report_card_record_id', 'action'], 'report_card_record_audit_logs_record_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_card_record_audit_logs');
    }
};
