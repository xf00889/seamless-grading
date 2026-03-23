<?php

namespace App\Actions\Admin\AcademicSetup\GradingPeriods;

use App\Models\GradingPeriod;

class SetGradingPeriodOpenStateAction
{
    public function handle(GradingPeriod $gradingPeriod, bool $isOpen): GradingPeriod
    {
        $gradingPeriod->forceFill(['is_open' => $isOpen])->save();

        return $gradingPeriod->refresh();
    }
}
