<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminMonitoring\SubmissionMonitoringIndexRequest;
use App\Services\AdminMonitoring\SubmissionMonitoringReadService;
use App\Support\SubmissionMonitoring\Navigation;
use Illuminate\Contracts\View\View;

class SubmissionMonitoringController extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly SubmissionMonitoringReadService $readService,
    ) {}

    public function __invoke(SubmissionMonitoringIndexRequest $request): View
    {
        return view('admin.submission-monitoring.index', [
            'navigationItems' => $this->navigation->items(),
            ...$this->readService->build($request->validated()),
        ]);
    }
}
