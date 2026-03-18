<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('sections', function (Blueprint $table): void {
            $table->unique(['id', 'school_year_id'], 'sections_id_school_year_unique');
        });

        Schema::table('teacher_loads', function (Blueprint $table): void {
            $table->dropForeign(['section_id']);
            $table->index(['section_id', 'school_year_id'], 'teacher_loads_section_school_year_index');
            $table->foreign(['section_id', 'school_year_id'], 'teacher_loads_section_school_year_foreign')
                ->references(['id', 'school_year_id'])
                ->on('sections')
                ->restrictOnDelete();
        });

        Schema::table('section_rosters', function (Blueprint $table): void {
            $table->dropForeign(['section_id']);
            $table->index(['section_id', 'school_year_id'], 'section_rosters_section_school_year_index');
            $table->foreign(['section_id', 'school_year_id'], 'section_rosters_section_school_year_foreign')
                ->references(['id', 'school_year_id'])
                ->on('sections')
                ->cascadeOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('section_rosters', function (Blueprint $table): void {
            $table->dropForeign('section_rosters_section_school_year_foreign');
            $table->dropIndex('section_rosters_section_school_year_index');
            $table->foreign('section_id')->references('id')->on('sections')->cascadeOnDelete();
        });

        Schema::table('teacher_loads', function (Blueprint $table): void {
            $table->dropForeign('teacher_loads_section_school_year_foreign');
            $table->dropIndex('teacher_loads_section_school_year_index');
            $table->foreign('section_id')->references('id')->on('sections')->restrictOnDelete();
        });

        Schema::table('sections', function (Blueprint $table): void {
            $table->dropUnique('sections_id_school_year_unique');
        });

        Schema::enableForeignKeyConstraints();
    }
};
