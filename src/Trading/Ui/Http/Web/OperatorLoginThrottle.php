<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

use Illuminate\Cache\RateLimiter;

/**
 * Limits failed login attempts per operator account and login step: once a step reaches the limit within a minute,
 * its further attempts are blocked without being checked until the minute has passed. The other step stays open.
 */
final readonly class OperatorLoginThrottle
{
    private const int DECAY_SECONDS = 60;

    public function __construct(
        private RateLimiter $rateLimiter,
        private int $maxFailedAttemptsPerMinute,
    ) {}

    public function isBlocked(OperatorLoginStep $step, string $account): bool
    {
        return $this->rateLimiter->tooManyAttempts($this->key($step, $account), $this->maxFailedAttemptsPerMinute);
    }

    public function recordFailure(OperatorLoginStep $step, string $account): void
    {
        $this->rateLimiter->hit($this->key($step, $account), self::DECAY_SECONDS);
    }

    public function clear(OperatorLoginStep $step, string $account): void
    {
        $this->rateLimiter->clear($this->key($step, $account));
    }

    private function key(OperatorLoginStep $step, string $account): string
    {
        return sprintf('trading:operator-login:%s:%s', $step->value, $account);
    }
}
