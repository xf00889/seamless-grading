<?php

use App\Http\Controllers\Admin\TemplateManagement\TemplateController;
use App\Http\Controllers\Admin\TemplateManagement\TemplateMappingController;
use App\Http\Controllers\Admin\TemplateManagementController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/template-management', TemplateManagementController::class)
    ->middleware('can:viewTemplateManagement,'.User::class)
    ->name('template-management');

Route::prefix('template-management')->name('template-management.')->group(function (): void {
    Route::get('/templates', [TemplateController::class, 'index'])
        ->name('templates.index');
    Route::get('/templates/create', [TemplateController::class, 'create'])
        ->name('templates.create');
    Route::post('/templates', [TemplateController::class, 'store'])
        ->name('templates.store');
    Route::get('/templates/{template}', [TemplateController::class, 'show'])
        ->name('templates.show');
    Route::get('/templates/{template}/history', [TemplateController::class, 'history'])
        ->name('templates.history');
    Route::post('/templates/{template}/activate', [TemplateController::class, 'activate'])
        ->name('templates.activate');
    Route::post('/templates/{template}/deactivate', [TemplateController::class, 'deactivate'])
        ->name('templates.deactivate');

    Route::get('/templates/{template}/mappings/edit', [TemplateMappingController::class, 'edit'])
        ->name('templates.mappings.edit');
    Route::put('/templates/{template}/mappings', [TemplateMappingController::class, 'update'])
        ->name('templates.mappings.update');
});
