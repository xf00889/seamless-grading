<?php

namespace Database\Factories;

use App\Enums\GradingSheetExportAuditAction;
use App\Models\GradingSheetExport;
use App\Models\GradingSheetExportAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradingSheetExportAuditLog>
 */
class GradingSheetExportAuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'grading_sheet_export_id' => GradingSheetExport::factory(),
            'acted_by' => User::factory(),
            'action' => GradingSheetExportAuditAction::Exported,
            'remarks' => fake()->sentence(),
            'metadata' => ['channel' => 'teacher'],
        ];
    }
}
