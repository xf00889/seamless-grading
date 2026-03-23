<?php

namespace App\Actions\Admin\AcademicSetup\GradingPeriods;

use App\Models\GradingPeriod;

class UpdateGradingPeriodAction
{
    public function handle(GradingPeriod $gradingPeriod, array $attributes): GradingPeriod
    {
        $gradingPeriod->fill($attributes)->save();

        return $gradingPeriod->refresh();
    }
}
