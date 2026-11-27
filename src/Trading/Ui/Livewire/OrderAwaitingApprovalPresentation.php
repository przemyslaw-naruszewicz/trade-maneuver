<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Livewire;

use DateTimeImmutable;
use DateTimeZone;
use TradeManeuver\Trading\Infrastructure\Order\ReadModel\OrderAwaitingApprovalReadModel;

/**
 * The display texts of an order awaiting approval and its trade signal. Decimals are shown as PostgreSQL returns
 * them, so no value passes through `float`, and times are shown in UTC.
 */
final readonly class OrderAwaitingApprovalPresentation
{
    private const string DATE = 'j M Y';

    private const string DATE_TIME = 'j M Y, H:i';

    private function __construct(
        public string $id,
        public string $instrumentSymbol,
        public string $listing,
        public string $status,
        public string $confidence,
        public string $horizon,
        public ?string $expectedMove,
        public string $referencePrice,
        public string $referencePriceAsOf,
        public string $analyticsApplication,
        public string $validUntil,
        public string $validUntilIso,
        public string $limitPrice,
        public string $quantity,
        public string $orderAmount,
        public string $version,
    ) {}

    public static function of(OrderAwaitingApprovalReadModel $order): self
    {
        $validUntil = self::utc($order->valid_until);

        return new self(
            $order->id,
            $order->instrument_symbol,
            sprintf('MIC %s, %s', $order->instrument_mic, $order->direction),
            'Awaiting approval',
            $order->confidence,
            self::horizonOf($order->horizon_sessions, $order->horizon_at),
            self::expectedMoveOf($order->expected_move_min, $order->expected_move_max),
            sprintf('%s %s', $order->reference_price_amount, $order->reference_price_currency),
            sprintf('As of %s UTC', self::utc($order->reference_price_as_of)->format(self::DATE_TIME)),
            $order->analytics_application,
            sprintf('%s UTC', $validUntil->format(self::DATE_TIME)),
            $validUntil->format(DATE_ATOM),
            sprintf('%s %s', $order->limit_price, $order->currency),
            (string) $order->quantity,
            sprintf('%s %s', $order->order_amount, $order->currency),
            sprintf('%s:%s', $order->status, $order->execution_progress),
        );
    }

    private static function horizonOf(?int $sessions, ?string $at): string
    {
        $parts = [];
        if ($sessions !== null) {
            $parts[] = sprintf('%d trading %s', $sessions, $sessions === 1 ? 'session' : 'sessions');
        }
        if ($at !== null) {
            $parts[] = sprintf('until %s', self::utc($at)->format(self::DATE));
        }

        return ucfirst(implode(', ', $parts));
    }

    private static function expectedMoveOf(?string $min, ?string $max): ?string
    {
        if ($min === null || $max === null) {
            return null;
        }

        return sprintf('%s %% to %s %%', self::signed($min), self::signed($max));
    }

    /**
     * A plus sign before a positive percentage; a negative one keeps its minus sign.
     */
    private static function signed(string $percentage): string
    {
        $isPositive = ! str_starts_with($percentage, '-') && trim($percentage, '0.') !== '';

        return $isPositive ? '+'.$percentage : $percentage;
    }

    private static function utc(string $time): DateTimeImmutable
    {
        $utc = new DateTimeZone('UTC');

        return new DateTimeImmutable($time, $utc)->setTimezone($utc);
    }
}
