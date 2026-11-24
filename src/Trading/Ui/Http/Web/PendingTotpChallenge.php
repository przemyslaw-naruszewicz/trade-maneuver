<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

use Illuminate\Contracts\Session\Session;

/**
 * The operator who passed the password step and still owes a TOTP code. The session is not authenticated yet.
 */
final class PendingTotpChallenge
{
    private const string SESSION_KEY = 'trading.operator.pending_totp_challenge';

    public static function start(Session $session, string $operatorId): void
    {
        $session->put(self::SESSION_KEY, $operatorId);
    }

    public static function operatorId(Session $session): ?string
    {
        $operatorId = $session->get(self::SESSION_KEY);

        return is_string($operatorId) ? $operatorId : null;
    }

    public static function end(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }
}
