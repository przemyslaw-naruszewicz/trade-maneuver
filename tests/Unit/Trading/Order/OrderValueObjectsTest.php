<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Order;

use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\Order\Exception\InvalidOrderValueException;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderQuantity;

final class OrderValueObjectsTest extends TestCase
{
    public function test_an_order_identity_is_a_uuid(): void
    {
        $this->assertSame('019a5a6e-0000-7000-8000-0000000000c1', (new OrderId('019a5a6e-0000-7000-8000-0000000000c1'))->toString());
    }

    public function test_an_order_identity_that_is_not_a_uuid_is_refused(): void
    {
        $this->expectException(InvalidOrderValueException::class);
        $this->expectExceptionMessage('The order identity "019a5a6e-0000-7000-8000-0000000000c1x" is not a UUID.');

        new OrderId('019a5a6e-0000-7000-8000-0000000000c1x');
    }

    public function test_an_order_identity_with_a_prefix_is_refused(): void
    {
        $this->expectException(InvalidOrderValueException::class);

        new OrderId('x019a5a6e-0000-7000-8000-0000000000c1');
    }

    public function test_an_order_quantity_of_one_unit_is_the_smallest(): void
    {
        $this->assertSame(1, OrderQuantity::of(1)->units());
    }

    public function test_an_order_quantity_of_zero_units_is_refused(): void
    {
        $this->expectException(InvalidOrderValueException::class);
        $this->expectExceptionMessage('The order quantity must be at least one unit, not 0.');

        OrderQuantity::of(0);
    }
}
