<?php

namespace Database\Factories;

use App\Enums\ReportCardRecordAuditAction;
use App\Models\ReportCardRecord;
use App\Models\ReportCardRecordAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCardRecordAuditLog>
 */
class ReportCardRecordAuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_card_record_id' => ReportCardRecord::factory(),
            'acted_by' => User::factory(),
            'action' => ReportCardRecordAuditAction::Exported,
            'remarks' => fake()->sentence(),
            'metadata' => ['channel' => 'adviser'],
        ];
    }
}
