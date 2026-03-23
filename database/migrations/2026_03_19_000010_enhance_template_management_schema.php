<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->foreignId('grade_level_id')->nullable()->after('document_type')->constrained()->restrictOnDelete();
            $table->string('scope_key')->default('global')->after('grade_level_id');
            $table->string('active_scope_key')->nullable()->after('scope_key');
            $table->string('file_disk')->default('local')->after('file_path');
            $table->timestamp('activated_at')->nullable()->after('is_active');
            $table->timestamp('deactivated_at')->nullable()->after('activated_at');
        });

        DB::table('templates')->update([
            'scope_key' => 'global',
            'file_disk' => 'local',
        ]);

        Schema::table('templates', function (Blueprint $table): void {
            $table->dropIndex('templates_document_type_code_is_active_index');
            $table->dropUnique('templates_document_type_code_version_unique');
            $table->unique(['document_type', 'scope_key', 'code', 'version'], 'templates_document_type_scope_code_version_unique');
            $table->unique('active_scope_key', 'templates_active_scope_key_unique');
            $table->index(['document_type', 'scope_key', 'is_active'], 'templates_document_type_scope_is_active_index');
            $table->index(['grade_level_id', 'document_type'], 'templates_grade_level_document_type_index');
        });

        Schema::table('template_field_maps', function (Blueprint $table): void {
            $table->dropIndex('template_field_maps_source_column_index');
            $table->renameColumn('source_column', 'target_cell');
        });

        Schema::table('template_field_maps', function (Blueprint $table): void {
            $table->string('target_cell')->nullable()->change();
            $table->index('target_cell', 'template_field_maps_target_cell_index');
        });
    }

    public function down(): void
    {
        Schema::table('template_field_maps', function (Blueprint $table): void {
            $table->dropIndex('template_field_maps_target_cell_index');
            $table->string('target_cell')->nullable(false)->change();
            $table->renameColumn('target_cell', 'source_column');
        });

        Schema::table('template_field_maps', function (Blueprint $table): void {
            $table->index('source_column');
        });

        Schema::table('templates', function (Blueprint $table): void {
            $table->dropIndex('templates_document_type_scope_is_active_index');
            $table->dropIndex('templates_grade_level_document_type_index');
            $table->dropUnique('templates_active_scope_key_unique');
            $table->dropUnique('templates_document_type_scope_code_version_unique');
            $table->unique(['document_type', 'code', 'version'], 'templates_document_type_code_version_unique');
            $table->index(['document_type', 'code', 'is_active'], 'templates_document_type_code_is_active_index');
            $table->dropConstrainedForeignId('grade_level_id');
            $table->dropColumn([
                'scope_key',
                'active_scope_key',
                'file_disk',
                'activated_at',
                'deactivated_at',
            ]);
        });
    }
};
