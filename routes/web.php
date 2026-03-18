<?php

use App\Http\Controllers\ProfileController;
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
            Route::view('/dashboard', 'dashboard', [
                'eyebrow' => 'Admin workspace',
                'title' => 'Admin Dashboard',
                'description' => 'Monitor the grading workflow foundation and access setup tools reserved for administrators.',
                'links' => [
                    [
                        'label' => 'Academic Setup',
                        'route' => 'admin.academic-setup',
                        'description' => 'Prepare school year, sections, grading periods, and templates in the next MVP slices.',
                    ],
                ],
            ])->middleware('can:viewAdminDashboard,'.User::class)->name('dashboard');

            Route::view('/academic-setup', 'protected-page', [
                'eyebrow' => 'Admin tools',
                'title' => 'Academic Setup',
                'description' => 'This protected area is reserved for administrators who manage school-wide setup and workflow controls.',
            ])->middleware('can:viewAcademicSetup,'.User::class)->name('academic-setup');
        });

        Route::prefix('teacher')->name('teacher.')->group(function () {
            Route::view('/dashboard', 'dashboard', [
                'eyebrow' => 'Teacher workspace',
                'title' => 'Teacher Dashboard',
                'description' => 'Review your grading workspace and open the teacher-only routes tied to your assigned loads.',
                'links' => [
                    [
                        'label' => 'My Teaching Loads',
                        'route' => 'teacher.loads.index',
                        'description' => 'Open the protected route for teacher-owned class and subject assignments.',
                    ],
                ],
            ])->middleware('can:viewTeacherDashboard,'.User::class)->name('dashboard');

            Route::view('/loads', 'protected-page', [
                'eyebrow' => 'Teacher tools',
                'title' => 'My Teaching Loads',
                'description' => 'This protected area is limited to teachers and represents the starting point for load-specific grading workflows.',
            ])->middleware('can:viewTeacherLoads,'.User::class)->name('loads.index');
        });

        Route::prefix('adviser')->name('adviser.')->group(function () {
            Route::view('/dashboard', 'dashboard', [
                'eyebrow' => 'Adviser workspace',
                'title' => 'Adviser Dashboard',
                'description' => 'Track your advisory workload and use adviser-only screens for section-facing responsibilities.',
                'links' => [
                    [
                        'label' => 'Advisory Sections',
                        'route' => 'adviser.sections.index',
                        'description' => 'Open the protected route for adviser-owned sections and consolidations.',
                    ],
                ],
            ])->middleware('can:viewAdviserDashboard,'.User::class)->name('dashboard');

            Route::view('/sections', 'protected-page', [
                'eyebrow' => 'Adviser tools',
                'title' => 'Advisory Sections',
                'description' => 'This protected area is limited to advisers who work with their own advisory sections.',
            ])->middleware('can:viewAdvisorySections,'.User::class)->name('sections.index');
        });

        Route::prefix('registrar')->name('registrar.')->group(function () {
            Route::view('/dashboard', 'dashboard', [
                'eyebrow' => 'Registrar workspace',
                'title' => 'Registrar Dashboard',
                'description' => 'View registrar-only read access to official records without exposing teacher or adviser routes.',
                'links' => [
                    [
                        'label' => 'Student Records',
                        'route' => 'registrar.records.index',
                        'description' => 'Open the protected registrar route for official read-only records access.',
                    ],
                ],
            ])->middleware('can:viewRegistrarDashboard,'.User::class)->name('dashboard');

            Route::view('/records', 'protected-page', [
                'eyebrow' => 'Registrar tools',
                'title' => 'Student Records',
                'description' => 'This protected area is limited to registrars and acts as the read-only records foundation for the MVP.',
            ])->middleware('can:viewRegistrarRecords,'.User::class)->name('records.index');
        });
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
