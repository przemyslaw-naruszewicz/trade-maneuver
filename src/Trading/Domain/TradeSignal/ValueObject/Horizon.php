<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;

/**
 * The period over which the move is expected to play out: a number of trading sessions, a date, or both.
 */
final readonly class Horizon
{
    private const string DATE_FORMAT = 'Y-m-d';

    private function __construct(private ?int $sessions, private ?DateTimeImmutable $at) {}

    /**
     * @throws InvalidTradeSignalValueException
     */
    public static function of(?int $sessions, ?string $at): self
    {
        if ($sessions === null && $at === null) {
            throw new InvalidTradeSignalValueException('The horizon needs a number of trading sessions, a date, or both.');
        }
        if ($sessions !== null && $sessions < 1) {
            throw new InvalidTradeSignalValueException('The horizon sessions must be a positive integer.', 'sessions');
        }

        return new self($sessions, $at === null ? null : self::dateOf($at));
    }

    public function sessions(): ?int
    {
        return $this->sessions;
    }

    /**
     * The date as YYYY-MM-DD, if the horizon has one.
     */
    public function at(): ?string
    {
        return $this->at?->format(self::DATE_FORMAT);
    }

    private static function dateOf(string $at): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!'.self::DATE_FORMAT, $at, new DateTimeZone('UTC'));
        if ($date === false || $date->format(self::DATE_FORMAT) !== $at) {
            throw new InvalidTradeSignalValueException('The horizon date must be a date such as 2026-11-20.', 'at');
        }

        return $date;
    }
}
