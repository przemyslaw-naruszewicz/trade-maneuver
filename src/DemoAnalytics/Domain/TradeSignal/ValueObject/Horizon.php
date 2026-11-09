<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject;

use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;

/**
 * The horizon as a number of trading sessions.
 */
final readonly class Horizon
{
    private const string SESSIONS_PATTERN = '/^[1-9]\d{0,5}$/D';

    private int $sessions;

    /**
     * @throws InvalidTradeSignalException
     */
    public function __construct(string $sessions)
    {
        if (preg_match(self::SESSIONS_PATTERN, $sessions) !== 1) {
            throw new InvalidTradeSignalException('The horizon sessions must be a positive whole number, such as 5.');
        }
        $this->sessions = (int) $sessions;
    }

    public function sessions(): int
    {
        return $this->sessions;
    }
}
