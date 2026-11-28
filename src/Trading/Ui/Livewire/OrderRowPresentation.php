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
        $status = OrderStatus::from($order->status);

        return new self(
            $order->id,
            $order->instrument_symbol,
            $order->instrument_mic,
            self::statusLabelOf($status),
            self::statusClassOf($status),
            self::executionProgressOf(ExecutionProgress::from($order->execution_progress), $order->quantity),
            sprintf('%s %s', $order->limit_price, $order->currency),
            self::utc($order->valid_until),
            $order->ended_at === null ? null : self::utc($order->ended_at),
            sprintf('%s:%s', $order->status, $order->execution_progress),
        );
    }

    private static function statusLabelOf(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::AwaitingApproval => 'Awaiting approval',
            OrderStatus::Approved => 'Approved',
            OrderStatus::RefusedByOperator => 'Refused by the operator',
        };
    }

    private static function statusClassOf(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::AwaitingApproval => 'status-awaiting-approval',
            OrderStatus::Approved => 'status-approved',
            OrderStatus::RefusedByOperator => 'status-refused',
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
