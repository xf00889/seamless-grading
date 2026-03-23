<?php

use App\Http\Controllers\Admin\AcademicSetup\GradeLevelController;
use App\Http\Controllers\Admin\AcademicSetup\GradingPeriodController;
use App\Http\Controllers\Admin\AcademicSetup\SchoolYearController;
use App\Http\Controllers\Admin\AcademicSetup\SectionController;
use App\Http\Controllers\Admin\AcademicSetup\SubjectController;
use App\Http\Controllers\Admin\AcademicSetupController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/academic-setup', AcademicSetupController::class)
    ->middleware('can:viewAcademicSetup,'.User::class)
    ->name('academic-setup');

Route::prefix('academic-setup')->name('academic-setup.')->group(function (): void {
    Route::post('/school-years/{school_year}/activate', [SchoolYearController::class, 'activate'])
        ->name('school-years.activate');
    Route::post('/school-years/{school_year}/deactivate', [SchoolYearController::class, 'deactivate'])
        ->name('school-years.deactivate');
    Route::resource('school-years', SchoolYearController::class);

    Route::post('/grading-periods/{grading_period}/open', [GradingPeriodController::class, 'open'])
        ->name('grading-periods.open');
    Route::post('/grading-periods/{grading_period}/close', [GradingPeriodController::class, 'close'])
        ->name('grading-periods.close');
    Route::resource('grading-periods', GradingPeriodController::class);

    Route::resource('grade-levels', GradeLevelController::class);

    Route::post('/sections/{section}/activate', [SectionController::class, 'activate'])
        ->name('sections.activate');
    Route::post('/sections/{section}/deactivate', [SectionController::class, 'deactivate'])
        ->name('sections.deactivate');
    Route::resource('sections', SectionController::class);

    Route::post('/subjects/{subject}/activate', [SubjectController::class, 'activate'])
        ->name('subjects.activate');
    Route::post('/subjects/{subject}/deactivate', [SubjectController::class, 'deactivate'])
        ->name('subjects.deactivate');
    Route::resource('subjects', SubjectController::class);
});
