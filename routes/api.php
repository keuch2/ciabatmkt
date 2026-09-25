<?php

use App\Http\Controllers\Admin\AdminHistoryController;
use App\Http\Controllers\Admin\DashboardAdminController;
use App\Http\Controllers\Admin\DivisionAdminController;
use App\Http\Controllers\Admin\DocsController;
use App\Http\Controllers\Admin\GroupAdminController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\RecordAdminController;
use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ParamValueController;
use App\Http\Controllers\RecordController;
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
    Route::get('menu', MenuController::class);
    Route::get('dashboards', [DashboardController::class, 'index']);
    Route::get('dashboards/{dashboard}', [DashboardController::class, 'show']);
    Route::put('dashboards/{dashboard}/params/{paramId}', [ParamValueController::class, 'update']);
    Route::delete('dashboards/{dashboard}/params/{paramId}', [ParamValueController::class, 'destroy']);
    Route::delete('dashboards/{dashboard}/params', [ParamValueController::class, 'destroyAll']);
    Route::get('dashboards/{dashboard}/history', [HistoryController::class, 'index']);

    // Registros compartidos (datos cargados desde la interfaz del dashboard).
    Route::get('dashboards/{dashboard}/data/{collection}', [RecordController::class, 'index']);
    Route::get('dashboards/{dashboard}/data/{collection}/changes', [RecordController::class, 'changes']);
    Route::post('dashboards/{dashboard}/data/{collection}/seed', [RecordController::class, 'seed']);
    Route::post('dashboards/{dashboard}/data/{collection}/replace', [RecordController::class, 'replace']);
    Route::put('dashboards/{dashboard}/data/{collection}/{recordId}', [RecordController::class, 'update']);
    Route::delete('dashboards/{dashboard}/data/{collection}/{recordId}', [RecordController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'active', 'super_admin'])->prefix('admin')->group(function () {
    Route::get('dashboards', [DashboardAdminController::class, 'index']);
    Route::post('dashboards/preview', [DashboardAdminController::class, 'preview']);
    Route::post('dashboards', [DashboardAdminController::class, 'store']);
    Route::put('dashboards/{dashboard}', [DashboardAdminController::class, 'update']);
    Route::delete('dashboards/{dashboard}', [DashboardAdminController::class, 'destroy']);
    Route::get('dashboards/{dashboard}/html', [DashboardAdminController::class, 'html']);
    Route::get('dashboards/{dashboard}/diagnostics', [DashboardAdminController::class, 'diagnostics']);

    Route::get('dashboards/{dashboard}/overview', OverviewController::class);
    Route::get('dashboards/{dashboard}/history', AdminHistoryController::class);
    Route::get('dashboards/{dashboard}/data', [RecordAdminController::class, 'summary']);
    Route::get('dashboards/{dashboard}/data-history', [RecordAdminController::class, 'history']);
    Route::get('dashboards/{dashboard}/data/{collection}', [RecordAdminController::class, 'records']);
    Route::get('dashboards/{dashboard}/data/{collection}/export', [RecordAdminController::class, 'export']);

    Route::get('docs', [DocsController::class, 'index']);
    Route::get('docs/dashboard-referencia.html', [DocsController::class, 'referenceDashboard']);

    Route::get('divisions', [DivisionAdminController::class, 'index']);
    Route::post('divisions', [DivisionAdminController::class, 'store']);
    Route::put('divisions/{division}', [DivisionAdminController::class, 'update']);
    Route::delete('divisions/{division}', [DivisionAdminController::class, 'destroy']);
    Route::post('divisions/{division}/groups', [GroupAdminController::class, 'store']);
    Route::put('divisions/{division}/groups/{group}', [GroupAdminController::class, 'update'])->scopeBindings();
    Route::delete('divisions/{division}/groups/{group}', [GroupAdminController::class, 'destroy'])->scopeBindings();

    Route::get('users', [UserAdminController::class, 'index']);
    Route::post('users', [UserAdminController::class, 'store']);
    Route::put('users/{user}', [UserAdminController::class, 'update']);
});
