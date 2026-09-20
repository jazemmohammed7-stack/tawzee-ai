<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'foundation')->name('home');

Route::view('/register', 'registration')->name('register');

Route::view('/login', 'auth.login')->name('login')->middleware(['guest', 'cache.headers:no_store;private']);
Route::view('/forgot-password', 'auth.forgot-password')->name('password.request')->middleware(['guest', 'cache.headers:no_store;private']);
Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset-password', compact('token')))->name('password.reset')->middleware(['guest', 'cache.headers:no_store;private']);
Route::get('/pending-setup', [SessionController::class, 'pending'])->middleware(['auth', 'tenant', 'cache.headers:no_store;private'])->name('setup.pending');
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');
