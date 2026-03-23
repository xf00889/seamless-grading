<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrar\RecordRepositoryIndexRequest;
use App\Models\Learner;
use App\Models\ReportCardRecord;
use App\Models\User;
use App\Services\RegistrarRecords\LearnerRecordHistoryReadService;
use App\Services\RegistrarRecords\RecordRepositoryReadService;
use App\Services\RegistrarRecords\RecordVerificationReadService;
use Illuminate\Contracts\View\View;

class RecordRepositoryController extends Controller
{
    public function __construct(
        private readonly RecordRepositoryReadService $repositoryReadService,
        private readonly LearnerRecordHistoryReadService $learnerHistoryReadService,
        private readonly RecordVerificationReadService $verificationReadService,
    ) {}

    public function index(RecordRepositoryIndexRequest $request): View
    {
        $this->authorize('viewRegistrarRecords', User::class);

        return view('registrar.records.index', $this->repositoryReadService->build($request->validated()));
    }

    public function learner(Learner $learner): View
    {
        $this->authorize('viewRegistrarRecords', User::class);

        return view('registrar.records.learner', $this->learnerHistoryReadService->build($learner));
    }

    public function show(ReportCardRecord $reportCardRecord): View
    {
        $this->authorize('viewAsRegistrar', $reportCardRecord);

        return view('registrar.records.show', $this->verificationReadService->build($reportCardRecord));
    }
}
