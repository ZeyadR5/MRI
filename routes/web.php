<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\CheckRole;
use Illuminate\Support\Facades\Route;

// Guest Auth Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Main schedule page
    Route::get('/', [BookingController::class, 'index'])->name('schedule.index');

    // Booking CRUD routes
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::match(['put', 'post'], '/bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    Route::match(['delete', 'post'], '/bookings/{booking}/delete', [BookingController::class, 'destroy'])->name('bookings.destroy.post');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

    // Search & Stats
    Route::get('/search', [BookingController::class, 'search'])->name('bookings.search');
    Route::get('/stats', [BookingController::class, 'stats'])->name('bookings.stats');

    // Super Admin Routes (Audit logs, User management, Old data clearing)
    Route::middleware(CheckRole::class . ':super_admin')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::match(['put', 'post'], '/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::match(['delete', 'post'], '/users/{user}/delete', [UserController::class, 'destroy'])->name('users.destroy.post');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::post('/clear-old-data', [BookingController::class, 'clearOldData'])->name('bookings.clear-old');
    });
});
