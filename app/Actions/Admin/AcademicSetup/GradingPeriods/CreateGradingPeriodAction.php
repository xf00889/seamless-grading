<?php

namespace App\Actions\Admin\AcademicSetup\GradingPeriods;

use App\Models\GradingPeriod;

class CreateGradingPeriodAction
{
    public function handle(array $attributes): GradingPeriod
    {
        return GradingPeriod::query()->create($attributes);
    }
}
