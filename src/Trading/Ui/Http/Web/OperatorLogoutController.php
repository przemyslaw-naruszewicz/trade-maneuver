<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

use Illuminate\Auth\AuthManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class OperatorLogoutController
{
    public function __construct(private AuthManager $auth) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $this->auth->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
