<?php

use App\Http\Controllers\Admin\UserManagement\TeacherLoadController;
use App\Http\Controllers\Admin\UserManagement\UserController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/user-management', UserManagementController::class)
    ->middleware('can:viewUserManagement,'.User::class)
    ->name('user-management');

Route::prefix('user-management')->name('user-management.')->group(function (): void {
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])
        ->name('users.activate');
    Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate'])
        ->name('users.deactivate');
    Route::resource('users', UserController::class);

    Route::post('/teacher-loads/{teacher_load}/activate', [TeacherLoadController::class, 'activate'])
        ->name('teacher-loads.activate');
    Route::post('/teacher-loads/{teacher_load}/deactivate', [TeacherLoadController::class, 'deactivate'])
        ->name('teacher-loads.deactivate');
    Route::resource('teacher-loads', TeacherLoadController::class);
});
