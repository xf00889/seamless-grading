<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\SubmissionMonitoring\LearnerMovementController;
use App\Http\Controllers\Admin\SubmissionMonitoring\QuarterWorkflowController;
use App\Http\Controllers\Admin\SubmissionMonitoringController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/submission-monitoring', SubmissionMonitoringController::class)
    ->middleware('can:viewSubmissionMonitoring,'.User::class)
    ->name('submission-monitoring');

Route::prefix('submission-monitoring')->name('submission-monitoring.')->group(function (): void {
    Route::get('/audit', AuditLogController::class)
        ->middleware('can:viewAuditLogs,'.User::class)
        ->name('audit');

    Route::get('/sections/{section}/learner-movements', [LearnerMovementController::class, 'index'])
        ->middleware('can:viewLearnerMovementsAsAdmin,section')
        ->name('sections.learner-movements.index');

    Route::put('/sections/{section}/learners/{section_roster}/learner-movements', [LearnerMovementController::class, 'update'])
        ->middleware('can:manageLearnerMovementsAsAdmin,section')
        ->name('sections.learner-movements.update');

    Route::post('/sections/{section}/grading-periods/{grading_period}/lock', [QuarterWorkflowController::class, 'lock'])
        ->middleware('can:manageQuarterLocks,'.User::class)
        ->name('sections.lock');

    Route::post('/sections/{section}/grading-periods/{grading_period}/reopen', [QuarterWorkflowController::class, 'reopen'])
        ->middleware('can:manageQuarterLocks,'.User::class)
        ->name('sections.reopen');
});
