<?php

use App\Enums\TemplateDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('document_type', 32)->default(TemplateDocumentType::Sf9->value);
            $table->unsignedInteger('version')->default(1);
            $table->string('file_path')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['code', 'version']);
            $table->index(['document_type', 'is_active']);
        });

        Schema::create('template_field_maps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('source_column');
            $table->string('default_value')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['template_id', 'field_key']);
            $table->index('source_column');
        });

        Schema::create('grading_sheet_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_load_id')->constrained()->restrictOnDelete();
            $table->foreignId('grading_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('template_version')->nullable();
            $table->string('file_name');
            $table->string('file_disk')->default('local');
            $table->string('file_path');
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->unique(['teacher_load_id', 'grading_period_id', 'version']);
            $table->index('exported_by');
        });

        Schema::create('report_card_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_roster_id')->constrained()->restrictOnDelete();
            $table->foreignId('grading_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('record_version')->default(1);
            $table->unsignedInteger('template_version')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['section_roster_id', 'grading_period_id', 'record_version']);
            $table->index('generated_by');
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('is_public');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('report_card_records');
        Schema::dropIfExists('grading_sheet_exports');
        Schema::dropIfExists('template_field_maps');
        Schema::dropIfExists('templates');
    }
};
