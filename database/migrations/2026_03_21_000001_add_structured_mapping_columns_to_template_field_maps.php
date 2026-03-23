<?php

use App\Enums\TemplateMappingKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_field_maps', function (Blueprint $table): void {
            $table->string('mapping_kind', 40)
                ->default(TemplateMappingKind::FixedCell->value)
                ->after('field_key');
            $table->string('sheet_name')->nullable()->after('target_cell');
            $table->json('mapping_config')->nullable()->after('sheet_name');
        });
    }

    public function down(): void
    {
        Schema::table('template_field_maps', function (Blueprint $table): void {
            $table->dropColumn([
                'mapping_kind',
                'sheet_name',
                'mapping_config',
            ]);
        });
    }
};
