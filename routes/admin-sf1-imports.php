<?php

use App\Http\Controllers\Admin\Sf1Imports\ImportBatchController;
use App\Http\Controllers\Admin\Sf1Imports\ImportBatchRowController;
use Illuminate\Support\Facades\Route;

Route::prefix('sf1-imports')->name('sf1-imports.')->group(function (): void {
    Route::get('/', [ImportBatchController::class, 'index'])->name('index');
    Route::get('/create', [ImportBatchController::class, 'create'])->name('create');
    Route::post('/', [ImportBatchController::class, 'store'])->name('store');
    Route::get('/{import_batch}', [ImportBatchController::class, 'show'])->name('show');
    Route::post('/{import_batch}/confirm', [ImportBatchController::class, 'confirm'])->name('confirm');

    Route::get('/{import_batch}/rows/{import_batch_row}/edit', [ImportBatchRowController::class, 'edit'])
        ->name('rows.edit');
    Route::put('/{import_batch}/rows/{import_batch_row}', [ImportBatchRowController::class, 'update'])
        ->name('rows.update');
});
