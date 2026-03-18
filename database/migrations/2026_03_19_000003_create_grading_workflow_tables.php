<?php

use App\Enums\ApprovalAction;
use App\Enums\GradeSubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_load_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_period_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default(GradeSubmissionStatus::Draft->value);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('adviser_remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['teacher_load_id', 'grading_period_id']);
            $table->index(['grading_period_id', 'status']);
        });

        Schema::create('quarterly_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grade_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_roster_id')->constrained()->restrictOnDelete();
            $table->decimal('grade', 5, 2)->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['grade_submission_id', 'section_roster_id']);
        });

        Schema::create('grade_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quarterly_grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('previous_grade', 5, 2)->nullable();
            $table->decimal('new_grade', 5, 2)->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index('changed_by');
        });

        Schema::create('approval_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grade_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32)->default(ApprovalAction::Submitted->value);
            $table->text('remarks')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['grade_submission_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('grade_change_logs');
        Schema::dropIfExists('quarterly_grades');
        Schema::dropIfExists('grade_submissions');
    }
};
