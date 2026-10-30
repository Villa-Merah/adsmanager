<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\MetaAdsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/auth/omni', [SsoController::class, 'login'])->middleware('throttle:10,1')->name('omni.login');
    Route::get('/auth/omni/callback', [SsoController::class, 'callback'])->middleware('throttle:20,1')->name('omni.callback');
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');
});
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/metrics', [DashboardController::class, 'metrics']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::get('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::put('/profile/password', [ProfileController::class, 'password']);
    Route::middleware('role:superadmin,kacab,marketing')->group(function () {
        Route::resource('campaigns', CampaignController::class)->only(['store', 'update', 'destroy']);
        Route::post('/imports/campaigns/preview', [CampaignController::class, 'preview'])->middleware('throttle:20,1');
        Route::post('/imports/campaigns', [CampaignController::class, 'import'])->middleware('throttle:10,1');
        Route::resource('leads', LeadController::class)->only(['store', 'update', 'destroy']);
        Route::post('/leads/{lead}/close', [LeadController::class, 'close']);
    });
    Route::middleware('role:superadmin')->group(function () {
        Route::resource('users', UserController::class)->only(['store', 'update', 'destroy']);
    });
    Route::middleware(['role:kacab,marketing', 'throttle:10,1'])->group(function () {
        Route::post('/meta/connect', [MetaAdsController::class, 'connect']);
        Route::post('/meta/sync', [MetaAdsController::class, 'sync']);
        Route::delete('/meta/disconnect', [MetaAdsController::class, 'disconnect']);
    });
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
