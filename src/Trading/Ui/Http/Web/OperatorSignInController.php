<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class OperatorSignInController
{
    public function create(): View
    {
        return view('trading.auth.sign-in');
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('login.totp');
    }
}
