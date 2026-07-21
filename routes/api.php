<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeployController;
use App\Http\Controllers\Api\ShortUrlController;
use Illuminate\Support\Facades\Route;

Route::post('/deploy', DeployController::class)
    ->middleware('throttle:3,1')
    ->name('api.deploy');

Route::prefix('auth')->group(function () {
    Route::post('/otp/request', [AuthController::class, 'requestOtp'])
        ->middleware('throttle:otp')
        ->name('api.auth.otp.request');

    Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify')
        ->name('api.auth.otp.verify');

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/urls', [ShortUrlController::class, 'index'])->name('api.urls.index');
    Route::post('/urls', [ShortUrlController::class, 'store'])->name('api.urls.store');
    Route::get('/urls/{url}', [ShortUrlController::class, 'show'])->name('api.urls.show');
    Route::put('/urls/{url}', [ShortUrlController::class, 'update'])->name('api.urls.update');
    Route::delete('/urls/{url}', [ShortUrlController::class, 'destroy'])->name('api.urls.destroy');
});
