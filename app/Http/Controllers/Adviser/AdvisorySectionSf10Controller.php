<?php

namespace App\Http\Controllers\Adviser;

use App\Actions\Adviser\YearEnd\ExportSf10RecordAction;
use App\Actions\Adviser\YearEnd\FinalizeSf10RecordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\ExportSf10RecordRequest;
use App\Http\Requests\Adviser\FinalizeSf10RecordRequest;
use App\Models\ReportCardRecord;
use App\Models\Section;
use App\Models\SectionRoster;
use App\Services\AdviserYearEnd\AdviserYearEndContextResolver;
use App\Services\AdviserYearEnd\Sf10PreviewDataBuilder;
use App\Support\AdviserReview\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdvisorySectionSf10Controller extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly AdviserYearEndContextResolver $contextResolver,
        private readonly Sf10PreviewDataBuilder $previewDataBuilder,
    ) {}

    public function show(Section $section, SectionRoster $sectionRoster): View
    {
        $this->authorize('viewSf10AsAdviser', $section);

        return view('adviser.sf10.show', [
            'navigationItems' => $this->navigation->items(),
            ...$this->previewDataBuilder->build($section, $sectionRoster),
        ]);
    }

    public function export(
        ExportSf10RecordRequest $request,
        Section $section,
        SectionRoster $sectionRoster,
        ExportSf10RecordAction $action,
    ): StreamedResponse {
        $record = $action->handle($request->user(), $section, $sectionRoster);

        return Storage::disk($record->file_disk)->download($record->file_path, $record->file_name);
    }

    public function finalize(
        FinalizeSf10RecordRequest $request,
        Section $section,
        SectionRoster $sectionRoster,
        ReportCardRecord $reportCardRecord,
        FinalizeSf10RecordAction $action,
    ): RedirectResponse {
        $action->handle($request->user(), $section, $sectionRoster, $reportCardRecord);

        return redirect()
            ->route('adviser.sections.sf10.show', [
                'section' => $section,
                'section_roster' => $sectionRoster,
            ])
            ->with('status', 'SF10 record finalized successfully and is now available to the registrar repository.');
    }

    public function download(
        Section $section,
        SectionRoster $sectionRoster,
        ReportCardRecord $reportCardRecord,
    ): StreamedResponse {
        $this->authorize('downloadAsAdviser', $reportCardRecord);
        $this->contextResolver->assertReportCardRecordScope($section, $sectionRoster, $reportCardRecord);

        abort_unless(
            filled($reportCardRecord->file_path)
            && Storage::disk($reportCardRecord->file_disk)->exists($reportCardRecord->file_path),
            404,
        );

        return Storage::disk($reportCardRecord->file_disk)
            ->download($reportCardRecord->file_path, $reportCardRecord->file_name);
    }
}
