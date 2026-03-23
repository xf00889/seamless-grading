<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('template_field_maps')) {
            return;
        }

        $this->alignFieldKeyColumn();
        $this->alignTargetCellColumn();

        Schema::table('template_field_maps', function (Blueprint $table): void {
            if (! Schema::hasColumn('template_field_maps', 'default_value')) {
                $table->string('default_value')->nullable()->after('target_cell');
            }

            if (! Schema::hasColumn('template_field_maps', 'is_required')) {
                $table->boolean('is_required')->default(true)->after('default_value');
            }
        });

        if (Schema::hasColumn('template_field_maps', 'is_required')) {
            DB::table('template_field_maps')
                ->whereNull('is_required')
                ->update(['is_required' => true]);
        }
    }

    public function down(): void
    {
        // Intentionally no-op. This migration repairs legacy drift and should not
        // attempt to infer how an older broken schema should be restored.
    }

    private function alignFieldKeyColumn(): void
    {
        if (Schema::hasColumn('template_field_maps', 'field_key')) {
            if (Schema::hasColumn('template_field_maps', 'field_name')) {
                DB::statement("
                    UPDATE template_field_maps
                    SET field_key = COALESCE(NULLIF(field_key, ''), field_name)
                    WHERE field_name IS NOT NULL
                ");

                Schema::table('template_field_maps', function (Blueprint $table): void {
                    $table->dropColumn('field_name');
                });
            }

            return;
        }

        if (Schema::hasColumn('template_field_maps', 'field_name')) {
            Schema::table('template_field_maps', function (Blueprint $table): void {
                $table->renameColumn('field_name', 'field_key');
            });

            return;
        }

        Schema::table('template_field_maps', function (Blueprint $table): void {
            $table->string('field_key')->nullable()->after('template_id');
        });
    }

    private function alignTargetCellColumn(): void
    {
        if (Schema::hasColumn('template_field_maps', 'target_cell')) {
            if (Schema::hasColumn('template_field_maps', 'source_column')) {
                DB::statement("
                    UPDATE template_field_maps
                    SET target_cell = COALESCE(NULLIF(target_cell, ''), source_column)
                    WHERE source_column IS NOT NULL
                ");

                Schema::table('template_field_maps', function (Blueprint $table): void {
                    $table->dropColumn('source_column');
                });
            }

            return;
        }

        if (Schema::hasColumn('template_field_maps', 'source_column')) {
            Schema::table('template_field_maps', function (Blueprint $table): void {
                $table->renameColumn('source_column', 'target_cell');
            });

            return;
        }

        Schema::table('template_field_maps', function (Blueprint $table): void {
            $table->string('target_cell')->nullable()->after('field_key');
        });
    }
};
