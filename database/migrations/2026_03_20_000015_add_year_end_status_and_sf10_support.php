<?php

use App\Enums\TemplateDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('section_rosters', function (Blueprint $table): void {
            $table->string('year_end_status', 32)->nullable()->after('withdrawn_on');
            $table->text('year_end_status_reason')->nullable()->after('year_end_status');
            $table->timestamp('year_end_status_set_at')->nullable()->after('year_end_status_reason');
            $table->foreignId('year_end_status_set_by')->nullable()->after('year_end_status_set_at')->constrained('users')->nullOnDelete();
            $table->index(['section_id', 'year_end_status'], 'section_rosters_section_year_end_status_index');
        });

        Schema::table('report_card_records', function (Blueprint $table): void {
            $table->string('document_type', 32)->default(TemplateDocumentType::Sf9->value)->after('grading_period_id');
            $table->index('document_type', 'report_card_records_document_type_index');
        });

        DB::table('report_card_records')
            ->orderBy('id')
            ->chunkById(100, function ($records): void {
                foreach ($records as $record) {
                    $documentType = $record->template_id === null
                        ? TemplateDocumentType::Sf9->value
                        : (string) (DB::table('templates')
                            ->where('id', $record->template_id)
                            ->value('document_type') ?? TemplateDocumentType::Sf9->value);

                    DB::table('report_card_records')
                        ->where('id', $record->id)
                        ->update(['document_type' => $documentType]);
                }
            });

        Schema::table('report_card_records', function (Blueprint $table): void {
            $table->unique(
                ['section_roster_id', 'grading_period_id', 'document_type', 'record_version'],
                'report_card_records_roster_period_document_type_version_unique',
            );
            $table->index(
                ['section_id', 'grading_period_id', 'document_type'],
                'report_card_records_section_period_document_type_index',
            );
            $table->index(
                ['learner_id', 'grading_period_id', 'document_type'],
                'report_card_records_learner_period_document_type_index',
            );
            $table->index(
                ['section_roster_id', 'grading_period_id', 'document_type', 'is_finalized'],
                'report_card_records_roster_period_document_type_finalized_index',
            );

            $table->dropUnique('report_card_records_roster_period_version_unique');
            $table->dropIndex('report_card_records_section_period_index');
            $table->dropIndex('report_card_records_learner_period_index');
            $table->dropIndex('report_card_records_roster_period_finalized_index');
        });
    }

    public function down(): void
    {
        Schema::table('report_card_records', function (Blueprint $table): void {
            $table->unique(
                ['section_roster_id', 'grading_period_id', 'record_version'],
                'report_card_records_roster_period_version_unique',
            );
            $table->index(['section_id', 'grading_period_id'], 'report_card_records_section_period_index');
            $table->index(['learner_id', 'grading_period_id'], 'report_card_records_learner_period_index');
            $table->index(
                ['section_roster_id', 'grading_period_id', 'is_finalized'],
                'report_card_records_roster_period_finalized_index',
            );

            $table->dropUnique('report_card_records_roster_period_document_type_version_unique');
            $table->dropIndex('report_card_records_section_period_document_type_index');
            $table->dropIndex('report_card_records_learner_period_document_type_index');
            $table->dropIndex('report_card_records_roster_period_document_type_finalized_index');
            $table->dropIndex('report_card_records_document_type_index');

            $table->dropColumn('document_type');
        });

        Schema::table('section_rosters', function (Blueprint $table): void {
            $table->dropIndex('section_rosters_section_year_end_status_index');
            $table->dropConstrainedForeignId('year_end_status_set_by');
            $table->dropColumn([
                'year_end_status',
                'year_end_status_reason',
                'year_end_status_set_at',
            ]);
        });
    }
};
