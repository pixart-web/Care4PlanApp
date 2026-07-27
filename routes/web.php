<?php

use App\Http\Controllers\Admin\CarerController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\PlaceholderController as AdminPlaceholderController;
use App\Http\Controllers\Admin\ScaleController as AdminScaleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Carer\PlaceholderController as CarerPlaceholderController;
use App\Http\Controllers\Carer\PocketController;
use App\Http\Controllers\Carer\ProfileController;
use App\Http\Controllers\Carer\ScaleController as CarerScaleController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'redirect'])->name('dashboard');

    Route::prefix('admin')->name('admin.')->middleware('role:ADMIN,EDITOR')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
        Route::get('/scales/events', [AdminScaleController::class, 'events'])->name('scales.events');
        Route::post('/scales/{service}/cancel', [AdminScaleController::class, 'cancel'])->name('scales.cancel');
        Route::resource('scales', AdminScaleController::class)->only(['index', 'store', 'update', 'destroy'])->parameters(['scales' => 'service']);
        Route::resource('carers', CarerController::class);
        Route::get('/carers-tools/generate-color', [CarerController::class, 'generateColor'])->name('carers.generate-color');
        Route::resource('patients', PatientController::class);
        Route::get('/reports', AdminPlaceholderController::class)->defaults('page', 'reports')->name('reports');
        Route::get('/messages', AdminPlaceholderController::class)->defaults('page', 'messages')->name('messages');
        Route::get('/settings', AdminPlaceholderController::class)->defaults('page', 'settings')->name('settings');

        Route::resource('users', UserController::class)->middleware('role:ADMIN');
    });

    Route::prefix('carer')->name('carer.')->middleware('role:CARER')->group(function () {
        Route::get('/scales', [CarerScaleController::class, 'index'])->name('scales.index');
        Route::get('/scales/events', [CarerScaleController::class, 'events'])->name('scales.events');
        Route::post('/scales/{service}/done', [CarerScaleController::class, 'done'])->name('scales.done');
        Route::post('/scales/{service}/undo', [CarerScaleController::class, 'undo'])->name('scales.undo');
        Route::get('/pocket', PocketController::class)->name('pocket');
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/reports', CarerPlaceholderController::class)->defaults('page', 'reports')->name('reports');
        Route::get('/messages', CarerPlaceholderController::class)->defaults('page', 'messages')->name('messages');
    });
});
