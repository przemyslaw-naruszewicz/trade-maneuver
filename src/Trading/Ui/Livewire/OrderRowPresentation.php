<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Livewire;

use DateTimeImmutable;
use DateTimeZone;
use TradeManeuver\Trading\Domain\Order\Enum\ExecutionProgress;
use TradeManeuver\Trading\Domain\Order\Enum\OrderStatus;
use TradeManeuver\Trading\Infrastructure\Order\ReadModel\OrderOverviewReadModel;

/**
 * The display texts of an order in a table of the orders screen. Decimals are shown as PostgreSQL returns them, so no
 * value passes through `float`, and times are shown in UTC.
 */
final readonly class OrderRowPresentation
{
    private const string DATE_TIME = 'j M, H:i';

    private function __construct(
        public string $id,
        public string $instrumentSymbol,
        public string $instrumentMic,
        public string $status,
        public string $statusClass,
        public string $executionProgress,
        public string $limitPrice,
        public string $validUntil,
        public ?string $ended,
        // The browser marks the order when an update changes this value.
        public string $version,
    ) {}

    public static function of(OrderOverviewReadModel $order): self
    {
        [$statusLabel, $statusClass] = self::statusOf(OrderStatus::from($order->status));

        return new self(
            $order->id,
            $order->instrument_symbol,
            $order->instrument_mic,
            $statusLabel,
            $statusClass,
            self::executionProgressOf(ExecutionProgress::from($order->execution_progress), $order->quantity),
            sprintf('%s %s', $order->limit_price, $order->currency),
            self::utc($order->valid_until),
            $order->ended_at === null ? null : self::utc($order->ended_at),
            sprintf('%s:%s', $order->status, $order->execution_progress),
        );
    }

    /**
     * @return array{string, string} the status label and the CSS class of its glyph marker
     */
    private static function statusOf(OrderStatus $status): array
    {
        return match ($status) {
            OrderStatus::AwaitingApproval => ['Awaiting approval', 'status-awaiting-approval'],
            OrderStatus::Approved => ['Approved', 'status-approved'],
            OrderStatus::RefusedByOperator => ['Refused by the operator', 'status-refused'],
        };
    }

    /**
     * The progress and the executed quantity of the order's quantity. Execution adds partial and complete progress.
     */
    private static function executionProgressOf(ExecutionProgress $progress, int $quantity): string
    {
        return match ($progress) {
            ExecutionProgress::None => sprintf('None, 0 of %d', $quantity),
        };
    }

    private static function utc(string $time): string
    {
        $utc = new DateTimeZone('UTC');

        return sprintf('%s UTC', new DateTimeImmutable($time, $utc)->setTimezone($utc)->format(self::DATE_TIME));
    }
}
