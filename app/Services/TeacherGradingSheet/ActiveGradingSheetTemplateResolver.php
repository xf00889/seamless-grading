<?php

namespace App\Services\TeacherGradingSheet;

use App\Enums\TemplateDocumentType;
use App\Models\TeacherLoad;
use App\Models\Template;

class ActiveGradingSheetTemplateResolver
{
    public function resolve(TeacherLoad $teacherLoad): ?Template
    {
        $teacherLoad->loadMissing('section.gradeLevel');

        $gradeLevelTemplate = Template::query()
            ->with(['gradeLevel:id,name', 'fieldMaps'])
            ->where('document_type', TemplateDocumentType::GradingSheet)
            ->where('is_active', true)
            ->where('grade_level_id', $teacherLoad->section->grade_level_id)
            ->orderByDesc('version')
            ->first();

        if ($gradeLevelTemplate !== null) {
            return $gradeLevelTemplate;
        }

        return Template::query()
            ->with(['gradeLevel:id,name', 'fieldMaps'])
            ->where('document_type', TemplateDocumentType::GradingSheet)
            ->where('is_active', true)
            ->whereNull('grade_level_id')
            ->orderByDesc('version')
            ->first();
    }
}
