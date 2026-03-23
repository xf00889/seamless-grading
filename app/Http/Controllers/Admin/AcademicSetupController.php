<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\GradingPeriod;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\AcademicSetup\Navigation;
use Illuminate\Contracts\View\View;

class AcademicSetupController extends Controller
{
    public function __invoke(Navigation $navigation): View
    {
        $this->authorize('viewAcademicSetup', User::class);

        return view('admin.academic-setup.index', [
            'navigationItems' => $navigation->items(),
            'resourceCards' => [
                [
                    'label' => 'School Years',
                    'route' => 'admin.academic-setup.school-years.index',
                    'count' => SchoolYear::query()->count(),
                    'status' => SchoolYear::query()->where('is_active', true)->count().' active',
                    'description' => 'Manage academic calendars and keep the active year clearly identified.',
                ],
                [
                    'label' => 'Grading Periods',
                    'route' => 'admin.academic-setup.grading-periods.index',
                    'count' => GradingPeriod::query()->count(),
                    'status' => GradingPeriod::query()->where('is_open', true)->count().' open',
                    'description' => 'Configure quarterly periods within their school-year bounds and sequence.',
                ],
                [
                    'label' => 'Grade Levels',
                    'route' => 'admin.academic-setup.grade-levels.index',
                    'count' => GradeLevel::query()->count(),
                    'status' => GradeLevel::query()->orderBy('sort_order')->value('code') ?? 'No levels yet',
                    'description' => 'Maintain the grade-level catalog used by sections and academic structure.',
                ],
                [
                    'label' => 'Sections',
                    'route' => 'admin.academic-setup.sections.index',
                    'count' => Section::query()->count(),
                    'status' => Section::query()->where('is_active', true)->count().' active',
                    'description' => 'Assign sections to school years, grade levels, and optional advisers.',
                ],
                [
                    'label' => 'Subjects',
                    'route' => 'admin.academic-setup.subjects.index',
                    'count' => Subject::query()->count(),
                    'status' => Subject::query()->where('is_active', true)->count().' active',
                    'description' => 'Keep the subject catalog ready for future load assignment and grading screens.',
                ],
            ],
        ]);
    }
}
