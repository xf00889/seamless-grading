<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('teacher_loads', function (Blueprint $table): void {
            $table->foreignId('school_year_id')->nullable()->after('teacher_id');
        });

        DB::table('teacher_loads')->update([
            'school_year_id' => DB::raw('(select school_year_id from sections where sections.id = teacher_loads.section_id)'),
        ]);

        Schema::table('teacher_loads', function (Blueprint $table): void {
            $table->dropUnique(['section_id', 'subject_id']);
            $table->dropIndex(['teacher_id', 'is_active']);
            $table->dropForeign(['section_id']);
            $table->unsignedBigInteger('school_year_id')->nullable(false)->change();
        });

        Schema::table('teacher_loads', function (Blueprint $table): void {
            $table->foreign('school_year_id')->references('id')->on('school_years')->restrictOnDelete();
            $table->foreign('section_id')->references('id')->on('sections')->restrictOnDelete();
            $table->unique(
                ['teacher_id', 'school_year_id', 'section_id', 'subject_id'],
                'teacher_loads_teacher_school_year_section_subject_unique',
            );
            $table->index(['teacher_id', 'school_year_id', 'is_active']);
            $table->index(['section_id', 'subject_id']);
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('teacher_loads', function (Blueprint $table): void {
            $table->dropUnique('teacher_loads_teacher_school_year_section_subject_unique');
            $table->dropIndex(['teacher_id', 'school_year_id', 'is_active']);
            $table->dropIndex(['section_id', 'subject_id']);
            $table->dropForeign(['school_year_id']);
            $table->dropForeign(['section_id']);
        });

        Schema::table('teacher_loads', function (Blueprint $table): void {
            $table->foreign('section_id')->references('id')->on('sections')->cascadeOnDelete();
            $table->unique(['section_id', 'subject_id']);
            $table->index(['teacher_id', 'is_active']);
            $table->dropColumn('school_year_id');
        });

        Schema::enableForeignKeyConstraints();
    }
};
