<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Infrastructure\Order\ReadModel\OrderOverviewReadModel;
use TradeManeuver\Trading\Ui\Livewire\OrderRowPresentation;

/**
 * How the tables of the orders screen word an order's status, its execution progress, and its times.
 */
final class OrderRowPresentationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function statuses(): iterable
    {
        yield 'awaiting approval' => ['awaiting_approval', 'Awaiting approval', 'status-awaiting-approval'];
        yield 'approved' => ['approved', 'Approved', 'status-approved'];
        yield 'refused by the operator' => ['refused_by_operator', 'Refused by the operator', 'status-refused'];
    }

    #[DataProvider('statuses')]
    public function test_each_status_has_its_label_and_its_glyph_class(string $status, string $label, string $class): void
    {
        $presentation = OrderRowPresentation::of($this->order(['status' => $status]));

        $this->assertSame($label, $presentation->status);
        $this->assertSame($class, $presentation->statusClass);
    }

    public function test_the_row_shows_the_execution_progress_the_limit_price_and_the_times_in_utc(): void
    {
        $presentation = OrderRowPresentation::of($this->order(['ended_at' => '2026-11-09 12:05:00.25+01']));

        $this->assertSame('None, 0 of 18', $presentation->executionProgress);
        $this->assertSame('11.05 EUR', $presentation->limitPrice);
        $this->assertSame('10 Nov, 17:00 UTC', $presentation->validUntil);
        $this->assertSame('9 Nov, 11:05 UTC', $presentation->ended);
        $this->assertSame('approved:none', $presentation->version);
    }

    public function test_an_order_that_has_not_ended_has_no_end_time(): void
    {
        $this->assertNull(OrderRowPresentation::of($this->order())->ended);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function order(array $overrides = []): OrderOverviewReadModel
    {
        $order = new OrderOverviewReadModel;
        $order->setRawAttributes([
            'id' => '019a5a6e-0000-7000-8000-0000000000c1',
            'status' => 'approved',
            'limit_price' => '11.05',
            'currency' => 'EUR',
            'quantity' => 18,
            'order_amount' => '200.00',
            'execution_progress' => 'none',
            'instrument_symbol' => 'DEMO_A',
            'instrument_mic' => 'DEMO',
            'valid_until' => '2026-11-10 17:00:00+00',
            'ended_at' => null,
            ...$overrides,
        ]);

        return $order;
    }
}
