<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

use Illuminate\Cache\RateLimiter;

/**
 * Limits login attempts per operator account and login step. Each attempt counts before it is checked, so that
 * concurrent requests cannot get more checks than the limit. A step that reaches the limit within a minute refuses
 * further attempts until the minute has passed; a successful attempt clears its step's count. The other step stays
 * open.
 */
final readonly class OperatorLoginThrottle
{
    private const int DECAY_SECONDS = 60;

    public function __construct(
        private RateLimiter $rateLimiter,
        private int $maxFailedAttemptsPerMinute,
    ) {}

    /**
     * Counts the attempt and says whether it may be checked.
     */
    public function admitAttempt(OperatorLoginStep $step, string $account): bool
    {
        return $this->rateLimiter->hit($this->key($step, $account), self::DECAY_SECONDS) <= $this->maxFailedAttemptsPerMinute;
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
