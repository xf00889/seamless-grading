<?php

namespace App\Actions\Admin\AcademicSetup\Sections;

use App\Models\Section;

class CreateSectionAction
{
    public function handle(array $attributes): Section
    {
        return Section::query()->create($attributes);
    }
}
