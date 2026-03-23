<?php

namespace App\Services\Admin;

use App\Services\AdminMonitoring\SubmissionMonitoringReadService;
use App\Support\Dashboard\BarChartPresenter;
use Illuminate\Support\Collection;

class AdminDashboardReadService
{
    public function __construct(
        private readonly SubmissionMonitoringReadService $submissionMonitoringReadService,
        private readonly AdminDashboardDemographicsReadService $adminDashboardDemographicsReadService,
        private readonly BarChartPresenter $barChartPresenter,
    ) {}

    public function build(): array
    {
        $monitoring = $this->submissionMonitoringReadService->build([]);

        $selectedSchoolYear = collect($monitoring['availableSchoolYears'])
            ->firstWhere('id', $monitoring['filters']['school_year_id']);
        $selectedGradingPeriod = $monitoring['selectedGradingPeriod'];
        $sectionSnapshots = collect($monitoring['sections']->items());
        $submissionSnapshots = collect($monitoring['submissionRows']->items());
        $demographics = $this->adminDashboardDemographicsReadService->build($selectedSchoolYear);

        $pendingSubmissionCount = (int) (
            $monitoring['totals']['missing_submissions']
            + $monitoring['totals']['draft_submissions']
            + $monitoring['totals']['submitted_submissions']
        );

        return [
            'headline' => [
                'eyebrow' => 'Admin workspace',
                'title' => 'Admin Dashboard',
                'description' => 'Run the grading workflow from one control center: academic setup, user assignments, imports, templates, monitoring, and audit visibility.',
            ],
            'stats' => [
                [
                    'label' => 'Active School Year',
                    'value' => $selectedSchoolYear?->name ?? 'Not configured',
                    'description' => $selectedGradingPeriod?->quarter?->label()
                        ? 'Current review window: '.$selectedGradingPeriod->quarter->label()
                        : 'Open a school year and grading period in academic setup to start quarter monitoring.',
                    'tone' => 'sky',
                    'icon' => 'calendar',
                    'status' => $selectedSchoolYear !== null ? 'Configured' : 'Setup needed',
                    'status_tone' => $selectedSchoolYear !== null ? 'sky' : 'amber',
                    'action_label' => 'Open academic setup',
                    'action_href' => route('admin.academic-setup'),
                ],
                [
                    'label' => 'Pending Subject Submissions',
                    'value' => number_format($pendingSubmissionCount),
                    'description' => 'Missing, draft, and submitted records still waiting to clear adviser review.',
                    'tone' => 'amber',
                    'icon' => 'clock',
                    'status' => $pendingSubmissionCount > 0 ? 'Needs attention' : 'Clear',
                    'status_tone' => $pendingSubmissionCount > 0 ? 'amber' : 'emerald',
                    'action_label' => $pendingSubmissionCount > 0 ? 'View details' : 'Open monitoring',
                    'action_href' => route('admin.submission-monitoring'),
                ],
                [
                    'label' => 'Returned Submissions',
                    'value' => number_format((int) $monitoring['totals']['returned_submissions']),
                    'description' => 'Teacher corrections currently in the return-and-resubmit path.',
                    'tone' => 'rose',
                    'icon' => 'undo',
                    'status' => $monitoring['totals']['returned_submissions'] > 0 ? 'Correction queue' : 'Stable',
                    'status_tone' => $monitoring['totals']['returned_submissions'] > 0 ? 'amber' : 'slate',
                    'action_label' => 'Review returned work',
                    'action_href' => route('admin.submission-monitoring'),
                ],
                [
                    'label' => 'Locked / Finalized Sections',
                    'value' => number_format((int) $monitoring['totals']['completed_sections']),
                    'description' => 'Sections already completed for the selected quarter, with approved or locked submissions and finalized SF9 records.',
                    'tone' => 'teal',
                    'icon' => 'lock',
                    'status' => $monitoring['totals']['completed_sections'] > 0 ? 'Official' : 'In progress',
                    'status_tone' => $monitoring['totals']['completed_sections'] > 0 ? 'teal' : 'slate',
                    'action_label' => 'View readiness',
                    'action_href' => route('admin.submission-monitoring'),
                ],
            ],
            'chart' => [
                'eyebrow' => 'Submission velocity',
                'title' => 'Workflow Snapshot',
                'description' => 'These bars reflect the current selected quarter across missing, submitted, returned, approved, and locked records.',
                'items' => $this->barChartPresenter->present([
                    ['label' => 'Missing', 'value' => (int) $monitoring['totals']['missing_submissions']],
                    ['label' => 'Draft', 'value' => (int) $monitoring['totals']['draft_submissions']],
                    ['label' => 'Submitted', 'value' => (int) $monitoring['totals']['submitted_submissions']],
                    ['label' => 'Returned', 'value' => (int) $monitoring['totals']['returned_submissions']],
                    ['label' => 'Approved', 'value' => (int) $monitoring['totals']['approved_submissions'], 'emphasis' => true],
                    ['label' => 'Locked', 'value' => (int) $monitoring['totals']['locked_submissions']],
                ]),
            ],
            'demographics' => $demographics,
            'needsAttention' => $this->attentionItems($sectionSnapshots, $submissionSnapshots),
            'readiness' => [
                'quarter_label' => $selectedGradingPeriod?->quarter?->label() ?? 'No grading period selected',
                'deadline' => $selectedGradingPeriod?->ends_on?->format('M d, Y') ?? 'No deadline configured',
                'completed_sections' => (int) $monitoring['totals']['completed_sections'],
                'late_submissions' => (int) $monitoring['totals']['late_submissions'],
                'finalized_sf9_records' => (int) $monitoring['totals']['finalized_sf9_records'],
                'required_sf9_roster_records' => (int) $monitoring['totals']['required_sf9_roster_records'],
            ],
            'spotlight' => [
                'eyebrow' => 'Official export readiness',
                'title' => (int) $monitoring['totals']['finalized_sf9_records'] >= (int) $monitoring['totals']['required_sf9_roster_records']
                    && (int) $monitoring['totals']['required_sf9_roster_records'] > 0
                    ? 'Quarter scope is aligned for official record generation.'
                    : 'Official learner record readiness still needs review.',
                'description' => sprintf(
                    '%s of %s required grade-eligible learner records are finalized from approved data in the current scope.',
                    number_format((int) $monitoring['totals']['finalized_sf9_records']),
                    number_format((int) $monitoring['totals']['required_sf9_roster_records']),
                ),
                'action_label' => 'Open monitoring',
                'action_href' => route('admin.submission-monitoring'),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $sectionSnapshots
     * @param  Collection<int, array<string, mixed>>  $submissionSnapshots
     * @return array<int, array<string, string>>
     */
    private function attentionItems(Collection $sectionSnapshots, Collection $submissionSnapshots): array
    {
        $sectionItems = $sectionSnapshots
            ->filter(function (array $section): bool {
                return $section['missing_submission_count'] > 0
                    || $section['returned_submission_count'] > 0
                    || $section['late_submission_count'] > 0
                    || $section['lock_blockers'] !== [];
            })
            ->take(3)
            ->map(fn (array $section): array => [
                'title' => $section['section_name'],
                'meta' => $section['grade_level_name'].' · '.$section['adviser_name'],
                'description' => collect([
                    $section['missing_submission_count'] > 0 ? $section['missing_submission_count'].' missing subject submission(s)' : null,
                    $section['returned_submission_count'] > 0 ? $section['returned_submission_count'].' returned submission(s)' : null,
                    $section['late_submission_count'] > 0 ? $section['late_submission_count'].' late submission item(s)' : null,
                    $section['lock_blockers'][0] ?? null,
                ])->filter()->implode(' · '),
                'route' => route('admin.submission-monitoring'),
                'badge' => $section['status']['label'],
            ]);

        $submissionItems = $submissionSnapshots
            ->filter(fn (array $row): bool => $row['is_late'] || in_array($row['status']['value'], ['missing', 'returned', 'submitted'], true))
            ->take(3)
            ->map(fn (array $row): array => [
                'title' => $row['subject_name'],
                'meta' => $row['section_name'].' · '.$row['teacher_name'],
                'description' => $row['late_reason']
                    ?? ($row['adviser_remarks'] ?: 'This submission still needs workflow attention.'),
                'route' => route('admin.submission-monitoring'),
                'badge' => $row['status']['label'],
            ]);

        return $sectionItems
            ->concat($submissionItems)
            ->take(6)
            ->values()
            ->all();
    }
}
