<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminMonitoring\AuditLogIndexRequest;
use App\Services\AdminMonitoring\AuditLogReadService;
use App\Support\SubmissionMonitoring\Navigation;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly Navigation $navigation,
        private readonly AuditLogReadService $readService,
    ) {}

    public function __invoke(AuditLogIndexRequest $request): View
    {
        return view('admin.submission-monitoring.audit', [
            'navigationItems' => $this->navigation->items(),
            ...$this->readService->build($request->validated()),
        ]);
    }
}
