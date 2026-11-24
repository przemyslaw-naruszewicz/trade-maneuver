<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use TradeManeuver\Trading\Ui\Http\Web\OperatorLogoutController;
use TradeManeuver\Trading\Ui\Http\Web\OperatorSignInController;
use TradeManeuver\Trading\Ui\Http\Web\OperatorTotpChallengeController;
use TradeManeuver\Trading\Ui\Livewire\Start;

Route::middleware('web')->withoutMiddleware(PreventRequestForgery::class)->group(function (): void {
    Route::get('/login', [OperatorSignInController::class, 'create'])->name('login');
    Route::post('/login', [OperatorSignInController::class, 'store'])->name('login.store');
    Route::get('/login/totp', [OperatorTotpChallengeController::class, 'create'])->name('login.totp');
    Route::post('/login/totp', [OperatorTotpChallengeController::class, 'store'])->name('login.totp.store');
    Route::view('/register', 'trading.auth.sign-in')->name('register');
    Route::post('/logout', OperatorLogoutController::class)->name('logout');

    Route::livewire('/', Start::class)->name('panel.start');
});
