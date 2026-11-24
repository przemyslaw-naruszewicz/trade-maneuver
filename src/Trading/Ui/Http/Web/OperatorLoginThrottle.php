<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

use Illuminate\Cache\RateLimiter;

/**
 * Limits failed login attempts per operator account: once the limit is reached within a minute, further attempts
 * are blocked without being checked until the minute has passed.
 */
final readonly class OperatorLoginThrottle
{
    private const int DECAY_SECONDS = 60;

    public function __construct(
        private RateLimiter $rateLimiter,
        private int $maxFailedAttemptsPerMinute,
    ) {}

    public function isBlocked(string $account): bool
    {
        return $this->rateLimiter->tooManyAttempts($this->key($account), $this->maxFailedAttemptsPerMinute);
    }

    public function recordFailure(string $account): void
    {
        $this->rateLimiter->hit($this->key($account), self::DECAY_SECONDS);
    }

    public function clear(string $account): void
    {
        $this->rateLimiter->clear($this->key($account));
    }

    private function key(string $account): string
    {
        return 'trading:operator-login:'.$account;
    }
}
