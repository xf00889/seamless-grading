<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_card_records', function (Blueprint $table): void {
            $table->foreignId('section_id')->nullable()->after('section_roster_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_year_id')->nullable()->after('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('learner_id')->nullable()->after('school_year_id')->constrained()->restrictOnDelete();
            $table->string('file_name')->nullable()->after('template_version');
            $table->string('file_disk')->default('local')->after('file_name');
            $table->string('file_path')->nullable()->after('file_disk');
            $table->boolean('is_finalized')->default(false)->after('file_path');
            $table->timestamp('finalized_at')->nullable()->after('is_finalized');
            $table->foreignId('finalized_by')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
            $table->index(['section_id', 'grading_period_id'], 'report_card_records_section_period_index');
            $table->index(['learner_id', 'grading_period_id'], 'report_card_records_learner_period_index');
            $table->index(['section_roster_id', 'grading_period_id', 'is_finalized'], 'report_card_records_roster_period_finalized_index');
        });

        DB::table('report_card_records')->update([
            'section_id' => DB::raw('(select section_rosters.section_id from section_rosters where section_rosters.id = report_card_records.section_roster_id)'),
            'school_year_id' => DB::raw('(select section_rosters.school_year_id from section_rosters where section_rosters.id = report_card_records.section_roster_id)'),
            'learner_id' => DB::raw('(select section_rosters.learner_id from section_rosters where section_rosters.id = report_card_records.section_roster_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('report_card_records', function (Blueprint $table): void {
            $table->dropIndex('report_card_records_section_period_index');
            $table->dropIndex('report_card_records_learner_period_index');
            $table->dropIndex('report_card_records_roster_period_finalized_index');
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropConstrainedForeignId('learner_id');
            $table->dropConstrainedForeignId('school_year_id');
            $table->dropConstrainedForeignId('section_id');
            $table->dropColumn([
                'file_name',
                'file_disk',
                'file_path',
                'is_finalized',
                'finalized_at',
            ]);
        });
    }
};
