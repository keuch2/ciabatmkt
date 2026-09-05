<?php

use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('login', LoginController::class)->middleware('throttle:10,1');
    Route::post('forgot-password', ForgotPasswordController::class)->middleware('throttle:5,1');
    Route::post('reset-password', ResetPasswordController::class)->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', LogoutController::class);
        Route::get('me', MeController::class);
    });
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    // Semana 2 y 3: /dashboards, /dashboards/{id}, params, history.
});

Route::middleware(['auth:sanctum', 'active', 'super_admin'])->prefix('admin')->group(function () {
    Route::get('users', [UserAdminController::class, 'index']);
    // Semana 2 y 4: /admin/dashboards, overview, history, users store/update.
});
