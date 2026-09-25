<?php
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\SchoolController;
Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
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
    ->get('/methodist', function () {
        return \Inertia\Inertia::render('methodist/Dashboard');
    })
    ->name('methodist.dashboard');
    Route::middleware(['auth', 'school_admin'])
    ->get('/school-admin', function () {
        return \Inertia\Inertia::render('school-admin/Dashboard');
    })
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