<?php

use App\Enums\LearnerStatusAuditAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_status_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_roster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 48)->default(LearnerStatusAuditAction::YearEndStatusUpdated->value);
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['section_roster_id', 'action'], 'learner_status_audit_logs_roster_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_status_audit_logs');
    }
};
