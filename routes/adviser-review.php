<?php

use App\Http\Controllers\Adviser\AdvisorySectionConsolidationController;
use App\Http\Controllers\Adviser\AdvisorySectionController;
use App\Http\Controllers\Adviser\AdvisorySectionLearnerMovementController;
use App\Http\Controllers\Adviser\AdvisorySectionSf10Controller;
use App\Http\Controllers\Adviser\AdvisorySectionSf9Controller;
use App\Http\Controllers\Adviser\AdvisorySectionSubmissionController;
use App\Http\Controllers\Adviser\AdvisorySectionTrackerController;
use App\Http\Controllers\Adviser\AdvisorySectionYearEndController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/sections', [AdvisorySectionController::class, 'index'])
    ->middleware('can:viewAdvisorySections,'.User::class)
    ->name('sections.index');

Route::get('/sections/{section}/grading-periods/{grading_period}/tracker', [AdvisorySectionTrackerController::class, 'show'])
    ->middleware('can:viewAsAdviser,section')
    ->name('sections.tracker');

Route::get('/sections/{section}/grading-periods/{grading_period}/submissions/{grade_submission}', [AdvisorySectionSubmissionController::class, 'show'])
    ->middleware('can:viewAsAdviser,grade_submission')
    ->name('sections.submissions.show');

Route::post('/sections/{section}/grading-periods/{grading_period}/submissions/{grade_submission}/approve', [AdvisorySectionSubmissionController::class, 'approve'])
    ->middleware('can:approveAsAdviser,grade_submission')
    ->name('sections.submissions.approve');

Route::post('/sections/{section}/grading-periods/{grading_period}/submissions/{grade_submission}/return', [AdvisorySectionSubmissionController::class, 'return'])
    ->middleware('can:returnAsAdviser,grade_submission')
    ->name('sections.submissions.return');

Route::get('/sections/{section}/grading-periods/{grading_period}/consolidation/learners', [AdvisorySectionConsolidationController::class, 'byLearner'])
    ->middleware('can:viewAsAdviser,section')
    ->name('sections.consolidation.learners');

Route::get('/sections/{section}/grading-periods/{grading_period}/consolidation/subjects', [AdvisorySectionConsolidationController::class, 'bySubject'])
    ->middleware('can:viewAsAdviser,section')
    ->name('sections.consolidation.subjects');

Route::get('/sections/{section}/grading-periods/{grading_period}/learners/{section_roster}/sf9', [AdvisorySectionSf9Controller::class, 'show'])
    ->middleware('can:viewSf9AsAdviser,section')
    ->name('sections.sf9.show');

Route::post('/sections/{section}/grading-periods/{grading_period}/learners/{section_roster}/sf9/export', [AdvisorySectionSf9Controller::class, 'export'])
    ->middleware('can:exportSf9AsAdviser,section')
    ->name('sections.sf9.export');

Route::post('/sections/{section}/grading-periods/{grading_period}/learners/{section_roster}/sf9/finalize', [AdvisorySectionSf9Controller::class, 'finalize'])
    ->middleware('can:finalizeSf9AsAdviser,section')
    ->name('sections.sf9.finalize');

Route::get('/sections/{section}/grading-periods/{grading_period}/learners/{section_roster}/sf9/records/{report_card_record}/download', [AdvisorySectionSf9Controller::class, 'download'])
    ->middleware('can:downloadAsAdviser,report_card_record')
    ->name('sections.sf9.download');

Route::get('/sections/{section}/year-end', [AdvisorySectionYearEndController::class, 'index'])
    ->middleware('can:viewYearEndAsAdviser,section')
    ->name('sections.year-end.index');

Route::put('/sections/{section}/learners/{section_roster}/year-end-status', [AdvisorySectionYearEndController::class, 'update'])
    ->middleware('can:manageYearEndAsAdviser,section')
    ->name('sections.year-end.update');

Route::get('/sections/{section}/learner-movements', [AdvisorySectionLearnerMovementController::class, 'index'])
    ->middleware('can:viewLearnerMovementsAsAdviser,section')
    ->name('sections.learner-movements.index');

Route::put('/sections/{section}/learners/{section_roster}/learner-movements', [AdvisorySectionLearnerMovementController::class, 'update'])
    ->middleware('can:manageLearnerMovementsAsAdviser,section')
    ->name('sections.learner-movements.update');

Route::get('/sections/{section}/learners/{section_roster}/sf10', [AdvisorySectionSf10Controller::class, 'show'])
    ->middleware('can:viewSf10AsAdviser,section')
    ->name('sections.sf10.show');

Route::post('/sections/{section}/learners/{section_roster}/sf10/export', [AdvisorySectionSf10Controller::class, 'export'])
    ->middleware('can:exportSf10AsAdviser,section')
    ->name('sections.sf10.export');

Route::post('/sections/{section}/learners/{section_roster}/sf10/records/{report_card_record}/finalize', [AdvisorySectionSf10Controller::class, 'finalize'])
    ->middleware('can:finalizeSf10AsAdviser,section')
    ->name('sections.sf10.finalize');

Route::get('/sections/{section}/learners/{section_roster}/sf10/records/{report_card_record}/download', [AdvisorySectionSf10Controller::class, 'download'])
    ->middleware('can:downloadAsAdviser,report_card_record')
    ->name('sections.sf10.download');
