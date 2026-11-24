<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Tests\TestCase;
use TradeManeuver\Trading\Ui\Http\Web\OperatorLoginStep;
use TradeManeuver\Trading\Ui\Http\Web\OperatorLoginThrottle;

final class OperatorLoginThrottleTest extends TestCase
{
    private const string ACCOUNT = 'operator@example.test';

    /**
     * Concurrent requests are admitted before any of them knows its outcome, so each admitted attempt counts at once.
     */
    public function test_after_five_admitted_attempts_without_an_outcome_the_sixth_is_refused(): void
    {
        $throttle = $this->app->make(OperatorLoginThrottle::class);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertTrue($throttle->admitAttempt(OperatorLoginStep::Totp, self::ACCOUNT), sprintf('Attempt %d was refused.', $attempt));
        }

        $this->assertFalse($throttle->admitAttempt(OperatorLoginStep::Totp, self::ACCOUNT));
        $this->assertTrue($throttle->admitAttempt(OperatorLoginStep::Password, self::ACCOUNT));
    }
}
