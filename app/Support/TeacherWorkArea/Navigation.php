<?php

namespace App\Support\TeacherWorkArea;

final class Navigation
{
    public function items(): array
    {
        return [
            [
                'label' => 'Overview',
                'route' => 'teacher.dashboard',
                'active' => 'teacher.dashboard',
            ],
            [
                'label' => 'My Teaching Loads',
                'route' => 'teacher.loads.index',
                'active' => ['teacher.loads.*', 'teacher.grade-entry.*', 'teacher.grading-sheet.*'],
            ],
            [
                'label' => 'Returned Submissions',
                'route' => 'teacher.returned-submissions.index',
                'active' => 'teacher.returned-submissions.*',
            ],
        ];
    }
}
