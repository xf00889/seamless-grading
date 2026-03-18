<?php

use App\Enums\EnrollmentStatus;
use App\Enums\ImportBatchRowStatus;
use App\Enums\ImportBatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_loads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['section_id', 'subject_id']);
            $table->index(['teacher_id', 'is_active']);
        });

        Schema::create('import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default(ImportBatchStatus::Uploaded->value);
            $table->string('source_file_name');
            $table->string('source_disk')->default('local');
            $table->string('source_path');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['section_id', 'status']);
        });

        Schema::create('import_batch_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('payload');
            $table->json('normalized_data')->nullable();
            $table->json('errors')->nullable();
            $table->string('status', 32)->default(ImportBatchRowStatus::Pending->value);
            $table->timestamps();

            $table->unique(['import_batch_id', 'row_number']);
            $table->index(['learner_id', 'status']);
        });

        Schema::create('section_rosters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained()->restrictOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('enrollment_status', 32)->default(EnrollmentStatus::Active->value);
            $table->date('enrolled_on')->nullable();
            $table->date('withdrawn_on')->nullable();
            $table->boolean('is_official')->default(true);
            $table->timestamps();

            $table->unique(['school_year_id', 'learner_id']);
            $table->index(['section_id', 'enrollment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_rosters');
        Schema::dropIfExists('import_batch_rows');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('teacher_loads');
    }
};
