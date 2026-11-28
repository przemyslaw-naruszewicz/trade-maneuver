<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order\Support;

use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\Repository\Exception\OrderChangedConcurrentlyException;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;

/**
 * An order repository whose save finds that another decision changed the order after it was loaded.
 */
final readonly class OrderChangedOnSave implements OrderRepositoryInterface
{
    public function __construct(private OrderRepositoryInterface $orders) {}

    public function nextIdentity(): OrderId
    {
        return $this->orders->nextIdentity();
    }

    public function add(Order $order): void
    {
        $this->orders->add($order);
    }

    public function get(OrderId $id): Order
    {
        return $this->orders->get($id);
    }

    public function save(Order $order): void
    {
        throw OrderChangedConcurrentlyException::withId($order->id(), 1);
    }
}
