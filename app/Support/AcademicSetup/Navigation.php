<?php

namespace App\Support\AcademicSetup;

final class Navigation
{
    public function items(): array
    {
        return [
            [
                'label' => 'Overview',
                'route' => 'admin.academic-setup',
                'active' => 'admin.academic-setup',
            ],
            [
                'label' => 'School Years',
                'route' => 'admin.academic-setup.school-years.index',
                'active' => 'admin.academic-setup.school-years.*',
            ],
            [
                'label' => 'Grading Periods',
                'route' => 'admin.academic-setup.grading-periods.index',
                'active' => 'admin.academic-setup.grading-periods.*',
            ],
            [
                'label' => 'Grade Levels',
                'route' => 'admin.academic-setup.grade-levels.index',
                'active' => 'admin.academic-setup.grade-levels.*',
            ],
            [
                'label' => 'Sections',
                'route' => 'admin.academic-setup.sections.index',
                'active' => 'admin.academic-setup.sections.*',
            ],
            [
                'label' => 'Subjects',
                'route' => 'admin.academic-setup.subjects.index',
                'active' => 'admin.academic-setup.subjects.*',
            ],
        ];
    }
}
