<?php

namespace App\Services\Teacher;

use App\Enums\GradeSubmissionStatus;
use App\Models\GradeSubmission;
use App\Models\SectionRoster;
use App\Models\TeacherLoad;
use App\Models\User;
use App\Support\Dashboard\BarChartPresenter;
use App\Support\TeacherWorkArea\Navigation;

class TeacherDashboardReadService
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly BarChartPresenter $barChartPresenter,
    ) {}

    public function build(User $teacher): array
    {
        $ownedTeacherLoads = TeacherLoad::query()->where('teacher_id', $teacher->id);
        $returnedSubmissions = GradeSubmission::query()
            ->where('status', GradeSubmissionStatus::Returned)
            ->whereHas('teacherLoad', fn ($query) => $query->where('teacher_id', $teacher->id));

        $recentReturnedSubmissions = (clone $returnedSubmissions)
            ->with([
                'gradingPeriod.schoolYear',
                'teacherLoad.schoolYear',
                'teacherLoad.section.gradeLevel',
                'teacherLoad.section.adviser',
                'teacherLoad.subject',
            ])
            ->orderByDesc('returned_at')
            ->limit(5)
            ->get();

        $summary = [
            'total_loads' => (clone $ownedTeacherLoads)->count(),
            'active_loads' => (clone $ownedTeacherLoads)->where('is_active', true)->count(),
            'official_learners' => $this->officialLearnerCount($teacher->id),
            'returned_submissions' => (clone $returnedSubmissions)->count(),
        ];

        $nextCorrection = $recentReturnedSubmissions->first();

        return [
            'navigationItems' => $this->navigation->items(),
            'summary' => $summary,
            'metrics' => [
                [
                    'eyebrow' => 'Teacher workspace',
                    'label' => 'Teaching loads',
                    'value' => number_format($summary['total_loads']),
                    'description' => 'All assigned subject loads currently tied to your account.',
                    'icon' => 'book',
                    'tone' => 'indigo',
                    'action_label' => 'View loads',
                    'action_href' => route('teacher.loads.index'),
                ],
                [
                    'eyebrow' => 'Operational scope',
                    'label' => 'Active loads',
                    'value' => number_format($summary['active_loads']),
                    'description' => 'Loads marked active and ready for current grading work.',
                    'icon' => 'check-circle',
                    'tone' => $summary['active_loads'] > 0 ? 'emerald' : 'slate',
                ],
                [
                    'eyebrow' => 'Official roster',
                    'label' => 'Official learners',
                    'value' => number_format($summary['official_learners']),
                    'description' => 'Grade-eligible learners visible from your owned sections only.',
                    'icon' => 'users',
                    'tone' => 'amber',
                ],
                [
                    'eyebrow' => 'Corrections queue',
                    'label' => 'Returned submissions',
                    'value' => number_format($summary['returned_submissions']),
                    'description' => 'Submissions waiting on your correction before they can move forward.',
                    'icon' => 'undo',
                    'tone' => $summary['returned_submissions'] > 0 ? 'rose' : 'emerald',
                    'action_label' => 'Review queue',
                    'action_href' => route('teacher.returned-submissions.index'),
                ],
            ],
            'chart' => [
                'eyebrow' => 'Submission velocity',
                'title' => 'Workload Snapshot',
                'description' => 'A quick balance view of your current teaching scope and correction load.',
                'items' => $this->barChartPresenter->present([
                    [
                        'label' => 'Loads',
                        'value' => $summary['total_loads'],
                        'value_label' => number_format($summary['total_loads']),
                    ],
                    [
                        'label' => 'Active',
                        'value' => $summary['active_loads'],
                        'value_label' => number_format($summary['active_loads']),
                    ],
                    [
                        'label' => 'Learners',
                        'value' => $summary['official_learners'],
                        'value_label' => number_format($summary['official_learners']),
                        'emphasis' => true,
                    ],
                    [
                        'label' => 'Returned',
                        'value' => $summary['returned_submissions'],
                        'value_label' => number_format($summary['returned_submissions']),
                    ],
                ]),
            ],
            'focus' => $nextCorrection !== null
                ? [
                    'eyebrow' => 'Correction focus',
                    'title' => $nextCorrection->teacherLoad->subject->name,
                    'description' => $nextCorrection->adviser_remarks ?: 'An adviser returned this submission for correction.',
                    'meta' => collect([
                        $nextCorrection->teacherLoad->schoolYear->name ?? null,
                        $nextCorrection->teacherLoad->section->gradeLevel->name ?? null,
                        $nextCorrection->teacherLoad->section->name ?? null,
                        $nextCorrection->gradingPeriod->quarter->label(),
                    ])->filter()->implode(' · '),
                    'action_label' => 'Open grade entry',
                    'action_href' => route('teacher.grade-entry.show', [
                        'teacher_load' => $nextCorrection->teacherLoad,
                        'grading_period' => $nextCorrection->gradingPeriod,
                    ]),
                ]
                : [
                    'eyebrow' => 'Correction focus',
                    'title' => 'No returned submissions waiting right now.',
                    'description' => 'Your dashboard is clear. You can move back into active loads or continue regular grade entry work.',
                    'meta' => 'Queue status · Stable',
                    'action_label' => 'Open teaching loads',
                    'action_href' => route('teacher.loads.index'),
                ],
            'recentReturnedSubmissions' => $recentReturnedSubmissions,
        ];
    }

    private function officialLearnerCount(int $teacherId): int
    {
        return SectionRoster::query()
            ->where('is_official', true)
            ->whereExists(function ($query) use ($teacherId): void {
                $query->selectRaw('1')
                    ->from('teacher_loads')
                    ->whereColumn('teacher_loads.section_id', 'section_rosters.section_id')
                    ->whereColumn('teacher_loads.school_year_id', 'section_rosters.school_year_id')
                    ->where('teacher_loads.teacher_id', $teacherId);
            })
            ->count();
    }
}
