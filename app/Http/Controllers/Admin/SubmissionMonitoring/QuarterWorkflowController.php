<?php

namespace App\Http\Controllers\Admin\SubmissionMonitoring;

use App\Actions\Admin\SubmissionMonitoring\LockQuarterRecordsAction;
use App\Actions\Admin\SubmissionMonitoring\ReopenQuarterRecordsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminMonitoring\LockQuarterRecordsRequest;
use App\Http\Requests\AdminMonitoring\ReopenQuarterRecordsRequest;
use App\Models\GradingPeriod;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;

class QuarterWorkflowController extends Controller
{
    public function lock(
        LockQuarterRecordsRequest $request,
        Section $section,
        GradingPeriod $gradingPeriod,
        LockQuarterRecordsAction $action,
    ): RedirectResponse {
        $result = $action->handle($request->user(), $section, $gradingPeriod);

        return redirect()
            ->route('admin.submission-monitoring', [
                'school_year_id' => $section->school_year_id,
                'grading_period_id' => $gradingPeriod->id,
                'section_id' => $section->id,
            ])
            ->with('status', sprintf(
                'Quarter records locked for %s. %d submission%s moved to locked status.',
                $section->name,
                $result['locked_count'],
                $result['locked_count'] === 1 ? '' : 's',
            ));
    }

    public function reopen(
        ReopenQuarterRecordsRequest $request,
        Section $section,
        GradingPeriod $gradingPeriod,
        ReopenQuarterRecordsAction $action,
    ): RedirectResponse {
        $result = $action->handle($request->user(), $section, $gradingPeriod, $request->validated('reason'));

        return redirect()
            ->route('admin.submission-monitoring', [
                'school_year_id' => $section->school_year_id,
                'grading_period_id' => $gradingPeriod->id,
                'section_id' => $section->id,
            ])
            ->with('status', sprintf(
                'Quarter records reopened for %s. %d submission%s returned for review and %d finalized report-card record%s were invalidated.',
                $section->name,
                $result['reopened_count'],
                $result['reopened_count'] === 1 ? '' : 's',
                $result['invalidated_report_card_count'],
                $result['invalidated_report_card_count'] === 1 ? '' : 's',
            ));
    }
}
