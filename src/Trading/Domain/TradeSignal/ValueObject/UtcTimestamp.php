<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;

/**
 * An ISO 8601 date and time in UTC, with at most microsecond precision.
 */
final readonly class UtcTimestamp
{
    private const string PATTERN = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?(?:Z|\+00:00)$/D';

    private const string FORMAT = 'Y-m-d\TH:i:s.u';

    private function __construct(private DateTimeImmutable $value) {}

    /**
     * @throws InvalidTradeSignalValueException
     */
    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value, $matches) !== 1) {
            throw self::invalid();
        }

        $normalized = $matches[1].'.'.str_pad($matches[2] ?? '', 6, '0');
        $dateTime = DateTimeImmutable::createFromFormat(self::FORMAT, $normalized, new DateTimeZone('UTC'));
        // createFromFormat() moves an impossible date such as 2026-02-30 to another day instead of failing.
        if ($dateTime === false || $dateTime->format(self::FORMAT) !== $normalized) {
            throw self::invalid();
        }

        return new self($dateTime);
    }

    public static function fromDateTime(DateTimeImmutable $value): self
    {
        return new self($value->setTimezone(new DateTimeZone('UTC')));
    }

    public function dateTime(): DateTimeImmutable
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value->format('Y-m-d\TH:i:s.u\Z');
    }

    private static function invalid(): InvalidTradeSignalValueException
    {
        return new InvalidTradeSignalValueException('The time must be an ISO 8601 date and time in UTC, such as 2026-11-07T09:30:00Z.');
    }
}
