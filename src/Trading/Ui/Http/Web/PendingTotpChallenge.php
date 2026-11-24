<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

use Illuminate\Contracts\Session\Session;

/**
 * The operator who passed the password step and still owes a TOTP code. The session is not authenticated yet.
 */
final readonly class PendingTotpChallenge
{
    private const string SESSION_KEY = 'trading.operator.pending_totp_challenge';

    private function __construct(
        public string $operatorId,
        public string $email,
    ) {}

    public static function start(Session $session, string $operatorId, string $email): void
    {
        $session->put(self::SESSION_KEY, ['operator_id' => $operatorId, 'email' => $email]);
    }

    public static function of(Session $session): ?self
    {
        $challenge = $session->get(self::SESSION_KEY);
        if (! is_array($challenge) || ! is_string($challenge['operator_id'] ?? null) || ! is_string($challenge['email'] ?? null)) {
            return null;
        }

        return new self($challenge['operator_id'], $challenge['email']);
    }

    public static function end(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }
}
