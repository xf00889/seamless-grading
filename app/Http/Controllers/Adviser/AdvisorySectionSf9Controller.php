<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\Adviser\Sf9\ExportSf9RecordAction;
use App\Actions\Adviser\Sf9\FinalizeSf9RecordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\ExportSf9RecordRequest;
use App\Http\Requests\Adviser\FinalizeSf9RecordRequest;
use App\Models\GradingPeriod;
use App\Models\ReportCardRecord;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Services\AdviserReview\AdviserQuarterContextResolver;
use App\Services\AdviserSf9\Sf9PreviewDataBuilder;
use App\Support\AdviserReview\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdvisorySectionSf9Controller extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly AdviserQuarterContextResolver $contextResolver,
        private readonly Sf9PreviewDataBuilder $previewDataBuilder,
    ) {}

    public function show(
        Section $section,
        GradingPeriod $gradingPeriod,
        SectionRoster $sectionRoster,
    ): View {
        $this->authorize('viewSf9AsAdviser', $section);

        return view('adviser.sf9.show', [
            'navigationItems' => $this->navigation->items(),
            ...$this->previewDataBuilder->build($section, $gradingPeriod, $sectionRoster),
        ]);
    }

    public function export(
        ExportSf9RecordRequest $request,
        Section $section,
        GradingPeriod $gradingPeriod,
        SectionRoster $sectionRoster,
        ExportSf9RecordAction $action,
    ): StreamedResponse {
        $record = $action->handle($request->user(), $section, $gradingPeriod, $sectionRoster);

        return Storage::disk($record->file_disk)->download($record->file_path, $record->file_name);
    }

    public function finalize(
        FinalizeSf9RecordRequest $request,
        Section $section,
        GradingPeriod $gradingPeriod,
        SectionRoster $sectionRoster,
        FinalizeSf9RecordAction $action,
    ): RedirectResponse {
        $action->handle($request->user(), $section, $gradingPeriod, $sectionRoster);

        return redirect()
            ->route('adviser.sections.sf9.show', [
                'section' => $section,
                'grading_period' => $gradingPeriod,
                'section_roster' => $sectionRoster,
            ])
            ->with('status', 'SF9 record finalized successfully.');
    }

    public function download(
        Section $section,
        GradingPeriod $gradingPeriod,
        SectionRoster $sectionRoster,
        ReportCardRecord $reportCardRecord,
    ): StreamedResponse {
        $this->authorize('downloadAsAdviser', $reportCardRecord);
        $this->contextResolver->assertReportCardRecordScope($section, $gradingPeriod, $sectionRoster, $reportCardRecord);

        abort_unless(
            filled($reportCardRecord->file_path)
            && Storage::disk($reportCardRecord->file_disk)->exists($reportCardRecord->file_path),
            404,
        );

        return Storage::disk($reportCardRecord->file_disk)
            ->download($reportCardRecord->file_path, $reportCardRecord->file_name);
    }
}
