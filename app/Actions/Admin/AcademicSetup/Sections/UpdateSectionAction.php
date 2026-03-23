<?php

namespace App\Actions\Admin\AcademicSetup\Sections;

use App\Models\Section;

class UpdateSectionAction
{
    public function handle(Section $section, array $attributes): Section
    {
        $section->fill($attributes)->save();

        return $section->refresh();
    }
}
