<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredCooperativeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Platform\ImpersonationController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisteredCooperativeController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredCooperativeController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/home', [AuthenticatedSessionController::class, 'home'])->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/impersonation/leave', [ImpersonationController::class, 'leave'])->name('impersonation.leave');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});
