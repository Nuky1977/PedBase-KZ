<?php
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SpreadsheetImportController;
use App\Http\Controllers\Teacher\ProfileController;
use App\Http\Controllers\TeacherEmploymentController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\TeacherDossierController;
use App\Http\Controllers\EducationReportController;
use App\UserRole;
Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        if ($request->user()->isAdmin()) {
            return to_route('admin.dashboard');
        }

        if (in_array($request->user()->role, [UserRole::SCHOOL_ADMIN, UserRole::METHODIST], true)) {
            return to_route('organization.index');
        }

        return \Inertia\Inertia::render('Dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';

Route::middleware(['auth', 'admin'])->prefix('/admin/imports')->name('admin.imports.')->group(function () {
    Route::get('/', [SpreadsheetImportController::class, 'index'])->name('index');
    Route::get('/{kind}/template', [SpreadsheetImportController::class, 'template'])
        ->whereIn('kind', ['schools', 'teachers'])->name('template');
    Route::post('/{kind}/preview', [SpreadsheetImportController::class, 'preview'])
        ->whereIn('kind', ['schools', 'teachers'])->middleware('throttle:20,1')->name('preview');
    Route::post('/{kind}/confirm', [SpreadsheetImportController::class, 'confirm'])
        ->whereIn('kind', ['schools', 'teachers'])->middleware('throttle:5,1')->name('confirm');
});

Route::middleware('auth')->group(function () {
    Route::get('/teacher/employment', [TeacherEmploymentController::class, 'mine'])->name('teacher.employment.index');

    Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
    Route::get('/organization/schools/{school}', [OrganizationController::class, 'school'])->name('organization.school');
    Route::get('/organization/access', [OrganizationController::class, 'access'])->name('organization.access');
    Route::post('/organization/access/{operation}', [OrganizationController::class, 'saveAccess'])
        ->whereIn('operation', ['district', 'school', 'school-admin', 'methodist'])->name('organization.access.save');

    Route::prefix('/dossiers/{teacher}')->name('dossiers.')->controller(TeacherDossierController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::get('/certificates/{course}', 'certificate')->whereNumber('course')->name('certificate');
        Route::post('/{kind}', 'store')->whereIn('kind', ['diplomas', 'courses', 'attestations'])->name('store');
        Route::post('/{kind}/{record}', 'update')->whereIn('kind', ['diplomas', 'courses', 'attestations'])->whereNumber('record')->name('update');
        Route::delete('/{kind}/{record}', 'destroy')->whereIn('kind', ['diplomas', 'courses', 'attestations'])->whereNumber('record')->name('destroy');
    });

    Route::get('/reports', [EducationReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export/{format}', [EducationReportController::class, 'export'])->whereIn('format', ['csv', 'xlsx'])->name('reports.export');

    Route::prefix('/admin/schools/{school}/employment')->name('admin.employment.')->group(function () {
        Route::get('/', [TeacherEmploymentController::class, 'school'])->name('school');
        Route::get('/{teacher}', [TeacherEmploymentController::class, 'show'])->name('show');
        Route::put('/{teacher}', [TeacherEmploymentController::class, 'save'])->name('save');
        Route::delete('/{teacher}', [TeacherEmploymentController::class, 'archive'])->name('archive');
        Route::post('/{teacher}/subjects', [TeacherEmploymentController::class, 'addSubject'])->name('subjects.store');
        Route::post('/{teacher}/assignments', [TeacherEmploymentController::class, 'addAssignment'])->name('assignments.store');
        Route::put('/{teacher}/assignments/{assignment}', [TeacherEmploymentController::class, 'updateAssignment'])->whereNumber('assignment')->name('assignments.update');
        Route::delete('/{teacher}/assignments/{assignment}', [TeacherEmploymentController::class, 'removeAssignment'])->whereNumber('assignment')->name('assignments.destroy');
    });
});

Route::middleware(['auth', 'admin'])
    ->get('/admin', DashboardController::class)
    ->name('admin.dashboard');

Route::middleware(['auth', 'admin'])
    ->get('/admin/users', [UserController::class, 'index'])
    ->name('admin.users.index');
    Route::middleware(['auth', 'admin'])
    ->get('/admin/users/create', [UserController::class, 'create'])
    ->name('admin.users.create');

Route::middleware(['auth', 'admin'])
    ->post('/admin/users', [UserController::class, 'store'])
    ->name('admin.users.store');

Route::middleware(['auth', 'admin'])
    ->get('/admin/users/{user}/edit', [UserController::class, 'edit'])
    ->name('admin.users.edit');

Route::middleware(['auth', 'admin'])
    ->put('/admin/users/{user}', [UserController::class, 'update'])
    ->name('admin.users.update');
    Route::middleware(['auth', 'admin'])
    ->delete('/admin/users/{user}', [UserController::class, 'destroy'])
    ->name('admin.users.destroy');
    Route::middleware(['auth', 'methodist'])
    ->get('/methodist', fn () => \Inertia\Inertia::render('methodist/Dashboard'))
    ->name('methodist.dashboard');
    Route::middleware(['auth', 'school_admin'])
    ->get('/school-admin', fn () => \Inertia\Inertia::render('school-admin/Dashboard'))
    ->name('school-admin.dashboard');
    Route::middleware(['auth', 'teacher'])
    ->get('/teacher', function () {
        return \Inertia\Inertia::render('teacher/Dashboard');
    })
    ->name('teacher.dashboard');
    Route::middleware(['auth', 'admin'])
    ->get('/admin/schools', [SchoolController::class, 'index'])
    ->name('admin.schools.index');
    Route::middleware(['auth', 'admin'])
    ->get('/admin/schools/create', [SchoolController::class, 'create'])
    ->name('admin.schools.create');
    Route::middleware(['auth', 'admin'])
    ->post('/admin/schools', [SchoolController::class, 'store'])
    ->name('admin.schools.store');
    Route::middleware(['auth', 'admin'])
    ->get('/admin/schools/{school}/edit', [SchoolController::class, 'edit'])
    ->name('admin.schools.edit');
    Route::middleware(['auth', 'admin'])
    ->put('/admin/schools/{school}', [SchoolController::class, 'update'])
    ->name('admin.schools.update');
    Route::middleware(['auth', 'admin'])
    ->patch('/admin/schools/{school}/status', [SchoolController::class, 'toggleStatus'])
    ->name('admin.schools.toggle-status');
    Route::middleware(['auth', 'admin'])
    ->get('/admin/schools/{school}/teachers', [SchoolController::class, 'teachers'])
    ->name('admin.schools.teachers');
    Route::middleware(['auth', 'admin'])
    ->post('/admin/schools/{school}/teachers', [SchoolController::class, 'attachTeacher'])
    ->name('admin.schools.teachers.attach');
    Route::middleware(['auth', 'admin'])
    ->delete(
        '/admin/schools/{school}/teachers/{teacher}',
        [SchoolController::class, 'detachTeacher']
    )
    ->name('admin.schools.teachers.detach');
    Route::middleware(['auth', 'admin'])
    ->patch(
        '/admin/schools/{school}/teachers/{teacher}/primary',
        [SchoolController::class, 'makeTeacherPrimary']
    )
    ->name('admin.schools.teachers.primary');
    Route::middleware(['auth', 'teacher'])
    ->get('/teacher/profile', [ProfileController::class, 'edit'])
    ->name('teacher.profile.edit');
    Route::middleware(['auth', 'teacher'])
    ->put('/teacher/profile', [ProfileController::class, 'update'])
    ->name('teacher.profile.update');
