<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->string('description')->nullable()->after('name');
            $table->dropUnique('templates_code_version_unique');
            $table->unique(['document_type', 'code', 'version'], 'templates_document_type_code_version_unique');
            $table->index(['document_type', 'code', 'is_active'], 'templates_document_type_code_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropIndex('templates_document_type_code_is_active_index');
            $table->dropUnique('templates_document_type_code_version_unique');
            $table->unique(['code', 'version']);
            $table->dropColumn('description');
        });
    }
};
