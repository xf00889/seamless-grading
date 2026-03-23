<?php

use App\Http\Controllers\Registrar\RecordRepositoryController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/records', [RecordRepositoryController::class, 'index'])
    ->middleware('can:viewRegistrarRecords,'.User::class)
    ->name('records.index');

Route::get('/records/learners/{learner}', [RecordRepositoryController::class, 'learner'])
    ->middleware('can:viewRegistrarRecords,'.User::class)
    ->name('records.learners.show');

Route::get('/records/{report_card_record}', [RecordRepositoryController::class, 'show'])
    ->middleware('can:viewAsRegistrar,report_card_record')
    ->name('records.show');
