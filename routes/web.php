<?php

use App\Http\Controllers\Admin\GarageController;
use App\Http\Controllers\Admin\RentalExtensionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserVerificationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect(AuthController::homeFor(auth()->user()))
    : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Pengaturan profil (Admin & User)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->middleware('role:user')->name('profile.destroy');

    Route::prefix('user')->name('user.')->middleware('role:user')->group(function () {
        Route::get('/rentals', [RentalController::class, 'index'])->name('rentals.index');
        Route::get('/rentals/new', [RentalController::class, 'create'])->name('rentals.create');
        Route::get('/history', [RentalController::class, 'history'])->name('rentals.history');
        Route::post('/rentals', [RentalController::class, 'store'])->name('rentals.store');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/garages', [GarageController::class, 'index'])->name('garages.index');
        Route::get('/garages/new', [GarageController::class, 'create'])->name('garages.create');
        Route::post('/garages', [GarageController::class, 'store'])->name('garages.store');
        Route::patch('/garages/prices', [GarageController::class, 'updatePrices'])->name('garages.prices');
        Route::get('/garages/{garage}', [GarageController::class, 'show'])->whereNumber('garage')->name('garages.show');

        Route::get('/verifications', [UserVerificationController::class, 'index'])->name('users.index');
        Route::patch('/verifications/{user}/approve', [UserVerificationController::class, 'approve'])->name('users.approve');
        Route::patch('/verifications/{user}/reject', [UserVerificationController::class, 'reject'])->name('users.reject');

        Route::get('/extensions', [RentalExtensionController::class, 'index'])->name('extensions.index');
        Route::get('/extensions/{rental}', [RentalExtensionController::class, 'edit'])->name('extensions.edit');
        Route::put('/extensions/{rental}', [RentalExtensionController::class, 'update'])->name('extensions.update');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    });
});
