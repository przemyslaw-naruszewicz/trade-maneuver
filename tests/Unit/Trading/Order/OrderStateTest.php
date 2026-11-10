<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Order;

use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Tests\Unit\Trading\Order\Support\OrderBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderSettings;
use TradeManeuver\Trading\Domain\Order\Dto\OrderStateDto;
use TradeManeuver\Trading\Domain\Order\Event\OrderCreated;
use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderQuantity;

final class OrderStateTest extends TestCase
{
    public function test_the_state_is_a_detached_copy(): void
    {
        $order = OrderBuilder::awaitingApproval();

        $this->assertNotSame($order->state(), $order->state());
    }

    public function test_the_settings_snapshot_cannot_be_changed(): void
    {
        $order = OrderBuilder::awaitingApproval();
        $settings = $order->state()->settings;

        try {
            (new ReflectionProperty($settings, 'orderAmount'))->setValue($settings, OrderAmount::of('5000.00', 'EUR'));
            $this->fail('The settings snapshot must not accept a new order amount.');
        } catch (Error $error) {
            $this->assertStringContainsString('Cannot modify readonly property', $error->getMessage());
        }

        $this->assertSame('1000.00', $order->state()->settings->orderAmount->amount());
    }

    public function test_the_state_cannot_be_given_another_snapshot(): void
    {
        $order = OrderBuilder::awaitingApproval();
        $state = $order->state();

        try {
            (new ReflectionProperty($state, 'settings'))->setValue($state, OrderBuilder::settings('5000.00', '5.00'));
            $this->fail('The state DTO must not accept another settings snapshot.');
        } catch (Error $error) {
            $this->assertStringContainsString('Cannot modify readonly property', $error->getMessage());
        }

        $this->assertEquals(OrderBuilder::settings(), $order->state()->settings);
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function immutableTypes(): iterable
    {
        yield 'state DTO' => [OrderStateDto::class];
        yield 'settings snapshot' => [OrderSettings::class];
        yield 'created event' => [OrderCreated::class];
        yield 'identity' => [OrderId::class];
        yield 'limit price' => [LimitPrice::class];
        yield 'quantity' => [OrderQuantity::class];
    }

    /**
     * The values these types hold are themselves immutable: Money, DateTimeImmutable, enums, scalars, and the
     * readonly value objects of the investment configuration and the trade signal.
     *
     * @param  class-string  $type
     */
    #[DataProvider('immutableTypes')]
    public function test_the_state_dto_and_its_values_are_readonly(string $type): void
    {
        $this->assertTrue((new ReflectionClass($type))->isReadOnly(), $type.' must be a readonly class.');
    }

    public function test_reconstitution_keeps_the_stored_state_and_records_no_domain_events(): void
    {
        $created = OrderBuilder::awaitingApproval();

        $reconstituted = Order::reconstitute($created->id(), $created->state());

        $this->assertSame([], $reconstituted->releaseEvents());
        $this->assertSame(OrderBuilder::ID, $reconstituted->id()->toString());
        $this->assertEquals($created->state(), $reconstituted->state());
    }
}
