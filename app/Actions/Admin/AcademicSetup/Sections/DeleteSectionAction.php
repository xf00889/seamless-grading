<?php

namespace App\Actions\Admin\AcademicSetup\Sections;

use App\Models\Section;
use Illuminate\Validation\ValidationException;

class DeleteSectionAction
{
    public function handle(Section $section): void
    {
        if (
            $section->teacherLoads()->exists()
            || $section->importBatches()->exists()
            || $section->sectionRosters()->exists()
        ) {
            throw ValidationException::withMessages([
                'record' => 'This section already has linked loads, imports, or roster records and cannot be deleted.',
            ]);
        }

        $section->delete();
    }
}
