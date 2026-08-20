<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use Inertia\Inertia;
Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
Route::middleware(['auth', 'admin'])->get('/admin', function () {
    return 'PedBase KZ — Admin панелі жұмыс істейді!';
});
Route::middleware(['auth', 'admin'])->get('/admin/users', function () {
    return Inertia::render('admin/Users', [
        'users' => User::query()
            ->select('id', 'name', 'username', 'role', 'email')
            ->orderBy('name')
            ->get(),
    ]);
})->name('admin.users.index');