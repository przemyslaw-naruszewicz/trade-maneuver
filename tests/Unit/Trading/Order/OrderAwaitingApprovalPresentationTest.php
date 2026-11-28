<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Order;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Infrastructure\Order\ReadModel\OrderOverviewReadModel;
use TradeManeuver\Trading\Ui\Livewire\OrderAwaitingApprovalPresentation;

/**
 * How the orders screen words the horizon and the expected move of an order's trade signal.
 */
final class OrderAwaitingApprovalPresentationTest extends TestCase
{
    /**
     * @return iterable<string, array{int|null, string|null, string}>
     */
    public static function horizons(): iterable
    {
        yield 'one trading session' => [1, null, '1 trading session'];
        yield 'only a date' => [null, '2026-11-28', 'Until 28 Nov 2026'];
        yield 'trading sessions and a date' => [5, '2026-11-20', '5 trading sessions, until 20 Nov 2026'];
    }

    #[DataProvider('horizons')]
    public function test_the_horizon_names_its_trading_sessions_and_its_date(?int $sessions, ?string $at, string $expected): void
    {
        $presentation = OrderAwaitingApprovalPresentation::of($this->order(['horizon_sessions' => $sessions, 'horizon_at' => $at]));

        $this->assertSame($expected, $presentation->horizon);
    }

    /**
     * @return iterable<string, array{string|null, string|null, string|null}>
     */
    public static function expectedMoves(): iterable
    {
        yield 'positive bounds get a plus sign' => ['1.5', '4.0', '+1.5 % to +4.0 %'];
        yield 'a negative bound keeps its minus sign and zero gets no sign' => ['-1.0', '0.0', '-1.0 % to 0.0 %'];
        yield 'no expected move' => [null, null, null];
        yield 'a missing bound' => ['1.5', null, null];
    }

    #[DataProvider('expectedMoves')]
    public function test_the_expected_move_shows_both_bounds_with_their_signs(?string $min, ?string $max, ?string $expected): void
    {
        $presentation = OrderAwaitingApprovalPresentation::of($this->order(['expected_move_min' => $min, 'expected_move_max' => $max]));

        $this->assertSame($expected, $presentation->expectedMove);
    }

    /**
     * @param  array<string, int|string|null>  $attributes
     */
    private function order(array $attributes): OrderOverviewReadModel
    {
        $order = new OrderOverviewReadModel;
        $order->forceFill([
            'id' => '01a21eb1-2641-7333-a02c-393c3438b162',
            'status' => 'awaiting_approval',
            'limit_price' => '10.20',
            'currency' => 'EUR',
            'quantity' => 98,
            'order_amount' => '1000.00',
            'execution_progress' => 'none',
            'instrument_symbol' => 'DEMO_A',
            'instrument_mic' => 'DEMO',
            'direction' => 'long',
            'confidence' => '0.85',
            'horizon_sessions' => 5,
            'horizon_at' => null,
            'expected_move_min' => null,
            'expected_move_max' => null,
            'reference_price_amount' => '10.00',
            'reference_price_currency' => 'EUR',
            'reference_price_as_of' => '2026-11-07 08:59:00+00',
            'valid_until' => '2026-11-10 16:00:00+00',
            'analytics_application' => 'demo-analytics',
            ...$attributes,
        ]);

        return $order;
    }
}
