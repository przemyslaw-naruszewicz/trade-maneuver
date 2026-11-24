<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class OperatorTotpChallengeController
{
    public function create(): View
    {
        return view('trading.auth.totp-challenge');
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('panel.start');
    }
}
