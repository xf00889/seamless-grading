<?php

namespace App\Services\AdviserYearEnd;

use App\Enums\TemplateDocumentType;
use App\Models\Section;
use App\Models\Template;

class ActiveSf10TemplateResolver
{
    public function resolve(Section $section): ?Template
    {
        $section->loadMissing('gradeLevel:id,name');

        $gradeLevelTemplate = Template::query()
            ->with(['gradeLevel:id,name', 'fieldMaps'])
            ->where('document_type', TemplateDocumentType::Sf10)
            ->where('is_active', true)
            ->where('grade_level_id', $section->grade_level_id)
            ->orderByDesc('version')
            ->first();

        if ($gradeLevelTemplate !== null) {
            return $gradeLevelTemplate;
        }

        return Template::query()
            ->with(['gradeLevel:id,name', 'fieldMaps'])
            ->where('document_type', TemplateDocumentType::Sf10)
            ->where('is_active', true)
            ->whereNull('grade_level_id')
            ->orderByDesc('version')
            ->first();
    }
}
