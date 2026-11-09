<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;

/**
 * A date and time in UTC, to the second, written as 2026-11-12T16:00:00Z.
 */
final readonly class UtcTime
{
    private const string FORMAT = 'Y-m-d\TH:i:s\Z';

    private function __construct(private DateTimeImmutable $value) {}

    /**
     * Drops the fraction of the second.
     */
    public static function fromDateTime(DateTimeImmutable $value): self
    {
        $utc = $value->setTimezone(new DateTimeZone('UTC'));

        return new self($utc->setTime((int) $utc->format('H'), (int) $utc->format('i'), (int) $utc->format('s')));
    }

    /**
     * @throws InvalidTradeSignalException
     */
    public static function fromString(string $value): self
    {
        $dateTime = DateTimeImmutable::createFromFormat('!'.self::FORMAT, $value, new DateTimeZone('UTC'));
        // createFromFormat() moves an impossible date such as 2026-11-31 to another day instead of failing.
        if ($dateTime === false || $dateTime->format(self::FORMAT) !== $value) {
            throw new InvalidTradeSignalException('The signal validity must be a date and time in UTC, such as 2026-11-12T16:00:00Z.');
        }

        return new self($dateTime);
    }

    public function plus(DateInterval $interval): self
    {
        return new self($this->value->add($interval));
    }

    public function isAfter(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function toString(): string
    {
        return $this->value->format(self::FORMAT);
    }
}
