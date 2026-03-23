<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Adviser\DashboardController as AdviserDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Registrar\DashboardController as RegistrarDashboardController;
use App\Http\Controllers\Teacher\DashboardController as TeacherDashboardController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::view('/access-denied', 'access-state', [
        'eyebrow' => 'Access restricted',
        'title' => 'Access Denied',
        'description' => 'Your account is signed in, but it does not have permission to open the page you requested.',
        'guidance' => 'Use the role-aware sidebar for the areas assigned to you, or contact an administrator if you believe this is incorrect.',
        'status' => 'Access denied',
    ])->name('access.denied');

    Route::view('/no-role-assigned', 'access-state', [
        'eyebrow' => 'Account setup required',
        'title' => 'No Role Assigned',
        'description' => 'Your account is authenticated, but it does not currently map to an allowed dashboard or protected area.',
        'guidance' => 'Contact an administrator to assign the correct role before continuing.',
        'status' => 'Role required',
    ])->name('access.no-role');

    Route::middleware('role.dashboard.access')->group(function () {
        Route::get('/dashboard', fn () => response()->noContent())
            ->middleware('dashboard.redirect')
            ->name('dashboard');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/dashboard', AdminDashboardController::class)
                ->middleware('can:viewAdminDashboard,'.User::class)
                ->name('dashboard');

            require __DIR__.'/admin-academic-setup.php';
            require __DIR__.'/admin-user-management.php';
            require __DIR__.'/admin-sf1-imports.php';
            require __DIR__.'/admin-template-management.php';
            require __DIR__.'/admin-submission-monitoring.php';
        });

        Route::prefix('teacher')->name('teacher.')->group(function () {
            Route::get('/dashboard', TeacherDashboardController::class)
                ->middleware('can:viewTeacherDashboard,'.User::class)
                ->name('dashboard');

            require __DIR__.'/teacher-work-area.php';
        });

        Route::prefix('adviser')->name('adviser.')->group(function () {
            Route::get('/dashboard', AdviserDashboardController::class)
                ->middleware('can:viewAdviserDashboard,'.User::class)
                ->name('dashboard');

            require __DIR__.'/adviser-review.php';
        });

        Route::prefix('registrar')->name('registrar.')->group(function () {
            Route::get('/dashboard', RegistrarDashboardController::class)
                ->middleware('can:viewRegistrarDashboard,'.User::class)
                ->name('dashboard');

            require __DIR__.'/registrar-records.php';
        });
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
