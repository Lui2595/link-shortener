<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\PendingUrlController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\UrlPanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::post('/shorten', [PendingUrlController::class, 'store'])->name('shorten');

Route::middleware('auth')->group(function () {
    Route::get('/urls', [UrlPanelController::class, 'index'])->name('urls.index');
    Route::post('/urls', [UrlPanelController::class, 'store'])->name('urls.store');
    Route::put('/urls/{url}', [UrlPanelController::class, 'update'])->name('urls.update');
    Route::delete('/urls/{url}', [UrlPanelController::class, 'destroy'])->name('urls.destroy');
    Route::post('/urls/commit-pending', [PendingUrlController::class, 'commit'])->name('urls.commit-pending');
});

Route::get('/{code}', RedirectController::class)
    ->where('code', '[a-z0-9]{8}')
    ->name('redirect');
