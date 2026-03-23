<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('section_rosters', function (Blueprint $table): void {
            $table->text('movement_reason')->nullable()->after('withdrawn_on');
            $table->timestamp('movement_recorded_at')->nullable()->after('movement_reason');
            $table->foreignId('movement_recorded_by')
                ->nullable()
                ->after('movement_recorded_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('section_rosters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('movement_recorded_by');
            $table->dropColumn([
                'movement_reason',
                'movement_recorded_at',
            ]);
        });
    }
};
