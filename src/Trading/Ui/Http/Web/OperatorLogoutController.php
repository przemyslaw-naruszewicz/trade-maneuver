<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

use Illuminate\Http\RedirectResponse;

final class OperatorLogoutController
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('login');
    }
}
