<?php

namespace App\Actions\Admin\AcademicSetup\Sections;

use App\Models\Section;

class SetSectionActivationAction
{
    public function handle(Section $section, bool $isActive): Section
    {
        $section->forceFill(['is_active' => $isActive])->save();

        return $section->refresh();
    }
}
