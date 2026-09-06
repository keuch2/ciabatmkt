<?php

use App\Http\Controllers\Admin\AdminHistoryController;
use App\Http\Controllers\Admin\DashboardAdminController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ParamValueController;
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
    Route::get('dashboards', [DashboardController::class, 'index']);
    Route::get('dashboards/{dashboard}', [DashboardController::class, 'show']);
    Route::put('dashboards/{dashboard}/params/{paramId}', [ParamValueController::class, 'update']);
    Route::delete('dashboards/{dashboard}/params/{paramId}', [ParamValueController::class, 'destroy']);
    Route::delete('dashboards/{dashboard}/params', [ParamValueController::class, 'destroyAll']);
    Route::get('dashboards/{dashboard}/history', [HistoryController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'active', 'super_admin'])->prefix('admin')->group(function () {
    Route::get('dashboards', [DashboardAdminController::class, 'index']);
    Route::post('dashboards/preview', [DashboardAdminController::class, 'preview']);
    Route::post('dashboards', [DashboardAdminController::class, 'store']);
    Route::put('dashboards/{dashboard}', [DashboardAdminController::class, 'update']);
    Route::delete('dashboards/{dashboard}', [DashboardAdminController::class, 'destroy']);

    Route::get('dashboards/{dashboard}/overview', OverviewController::class);
    Route::get('dashboards/{dashboard}/history', AdminHistoryController::class);

    Route::get('users', [UserAdminController::class, 'index']);
    Route::post('users', [UserAdminController::class, 'store']);
    Route::put('users/{user}', [UserAdminController::class, 'update']);
});
