<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\GradingPeriod;
use App\Models\TeacherLoad;
use App\Support\TeacherWorkArea\Navigation;
use Illuminate\Contracts\View\View;

class GradeEntryPageController extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
    ) {}

    public function __invoke(TeacherLoad $teacherLoad, GradingPeriod $gradingPeriod): View
    {
        abort_unless($teacherLoad->school_year_id === $gradingPeriod->school_year_id, 404);

        $teacherLoad->loadMissing([
            'schoolYear',
            'section.gradeLevel',
            'section.adviser',
            'subject',
        ]);

        return view('teacher.grade-entry.show', [
            'navigationItems' => $this->navigation->items(),
            'teacherLoad' => $teacherLoad,
            'gradingPeriod' => $gradingPeriod,
        ]);
    }
}
