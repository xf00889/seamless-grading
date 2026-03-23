<?php

use App\Http\Controllers\Teacher\GradeEntryPageController;
use App\Http\Controllers\Teacher\GradingSheetController;
use App\Http\Controllers\Teacher\ReturnedSubmissionController;
use App\Http\Controllers\Teacher\TeachingLoadController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/loads', [TeachingLoadController::class, 'index'])
    ->middleware('can:viewTeacherLoads,'.User::class)
    ->name('loads.index');

Route::get('/loads/{teacher_load}', [TeachingLoadController::class, 'show'])
    ->name('loads.show');

Route::get('/loads/{teacher_load}/grading-periods/{grading_period}/grades', GradeEntryPageController::class)
    ->middleware('can:enterGrades,teacher_load')
    ->name('grade-entry.show');

Route::get('/loads/{teacher_load}/grading-periods/{grading_period}/grading-sheet', [GradingSheetController::class, 'show'])
    ->middleware('can:previewGradingSheet,teacher_load')
    ->name('grading-sheet.show');

Route::post('/loads/{teacher_load}/grading-periods/{grading_period}/grading-sheet/export', [GradingSheetController::class, 'export'])
    ->middleware('can:exportGradingSheet,teacher_load')
    ->name('grading-sheet.export');

Route::get('/loads/{teacher_load}/grading-periods/{grading_period}/grading-sheet/exports/{grading_sheet_export}/download', [GradingSheetController::class, 'download'])
    ->middleware('can:download,grading_sheet_export')
    ->name('grading-sheet.download');

Route::get('/returned-submissions', [ReturnedSubmissionController::class, 'index'])
    ->middleware('can:viewTeacherReturnedSubmissions,'.User::class)
    ->name('returned-submissions.index');
