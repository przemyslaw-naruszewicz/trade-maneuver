<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Repository;

use TradeManeuver\Trading\Domain\Order\Exception\TradeSignalAlreadyHasAnOrderException;
use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\Repository\Exception\OrderDoesNotExistException;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;

interface OrderRepositoryInterface
{
    public function nextIdentity(): OrderId;

    /**
     * Refuses a second order for a trade signal, also under concurrent creation.
     *
     * @throws TradeSignalAlreadyHasAnOrderException
     */
    public function add(Order $order): void;

    /**
     * @throws OrderDoesNotExistException
     */
    public function get(OrderId $id): Order;
}
