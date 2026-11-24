<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use TradeManeuver\Trading\Ui\Http\Web\OperatorLogoutController;
use TradeManeuver\Trading\Ui\Http\Web\OperatorSignInController;
use TradeManeuver\Trading\Ui\Http\Web\OperatorTotpChallengeController;

Route::middleware('web')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('/login', [OperatorSignInController::class, 'create'])->name('login');
        Route::post('/login', [OperatorSignInController::class, 'store'])->name('login.store');
        Route::get('/login/totp', [OperatorTotpChallengeController::class, 'create'])->name('login.totp');
        Route::post('/login/totp', [OperatorTotpChallengeController::class, 'store'])->name('login.totp.store');
    });

    // Every panel page needs a session that passed both the password and the TOTP step.
    Route::middleware('auth')->group(function (): void {
        Route::post('/logout', OperatorLogoutController::class)->name('logout');

        Route::livewire('/', 'trading.start')->name('panel.start');
    });
});
