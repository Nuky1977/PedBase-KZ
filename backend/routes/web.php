<?php
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

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