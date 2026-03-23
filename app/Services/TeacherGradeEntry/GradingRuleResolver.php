<?php

namespace App\Services\TeacherGradeEntry;

use App\Models\SystemSetting;

class GradingRuleResolver
{
    public function resolve(): array
    {
        $defaults = config('grading.rules', []);
        $overrides = SystemSetting::query()
            ->where('key', 'grading.rules')
            ->value('value');

        $settings = array_merge(
            $defaults,
            is_array($overrides) ? $overrides : [],
        );

        return [
            'minimum' => (float) ($settings['minimum'] ?? 60),
            'maximum' => (float) ($settings['maximum'] ?? 100),
            'passing' => (float) ($settings['passing'] ?? 75),
            'decimal_places' => (int) ($settings['decimal_places'] ?? 2),
            'allow_blank_active_learners' => (bool) ($settings['allow_blank_active_learners'] ?? false),
            'allow_blank_in_drafts' => (bool) ($settings['allow_blank_in_drafts'] ?? true),
        ];
    }
}
