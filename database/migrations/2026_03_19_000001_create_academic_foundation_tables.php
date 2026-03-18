<?php

use App\Enums\EnrollmentStatus;
use App\Enums\LearnerSex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_years', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('grading_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_open')->default(false);
            $table->timestamps();

            $table->unique(['school_year_id', 'quarter']);
            $table->index(['school_year_id', 'is_open']);
        });

        Schema::create('grade_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->unique();
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('adviser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_year_id', 'name']);
            $table->index(['school_year_id', 'grade_level_id']);
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->boolean('is_core')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('learners', function (Blueprint $table): void {
            $table->id();
            $table->string('lrn', 32)->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('suffix')->nullable();
            $table->string('sex', 20)->default(LearnerSex::Male->value);
            $table->date('birth_date')->nullable();
            $table->string('enrollment_status', 32)->default(EnrollmentStatus::Active->value);
            $table->date('transfer_effective_date')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('enrollment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learners');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('grade_levels');
        Schema::dropIfExists('grading_periods');
        Schema::dropIfExists('school_years');
    }
};
