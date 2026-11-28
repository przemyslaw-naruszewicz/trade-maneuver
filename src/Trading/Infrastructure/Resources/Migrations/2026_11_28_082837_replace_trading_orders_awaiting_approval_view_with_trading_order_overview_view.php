<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The read side of orders in every status, each with its trade signal, replaces the read side of orders awaiting
     * approval. A view that joins two tables is not updatable, so PostgreSQL refuses every write through it (ADR-0004).
     */
    public function up(): void
    {
        DB::statement('DROP VIEW trading_orders_awaiting_approval');
        DB::statement('CREATE VIEW trading_order_overview AS '.$this->ordersWithTheirTradeSignals(['orders.ended_at']));
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS trading_order_overview');
        DB::statement('CREATE VIEW trading_orders_awaiting_approval AS '.$this->ordersWithTheirTradeSignals([])." WHERE orders.status = 'awaiting_approval'");
    }

    /**
     * @param  list<string>  $extraColumns
     */
    private function ordersWithTheirTradeSignals(array $extraColumns): string
    {
        $columns = [
            'orders.id',
            'orders.status',
            'orders.limit_price',
            'orders.currency',
            'orders.quantity',
            'orders.order_amount',
            'orders.execution_progress',
            'orders.trade_signal_id',
            ...$extraColumns,
            'trade_signals.instrument_symbol',
            'trade_signals.instrument_mic',
            'trade_signals.direction',
            'trade_signals.confidence',
            'trade_signals.horizon_sessions',
            'trade_signals.horizon_at',
            'trade_signals.expected_move_min',
            'trade_signals.expected_move_max',
            'trade_signals.reference_price_amount',
            'trade_signals.reference_price_currency',
            'trade_signals.reference_price_as_of',
            'trade_signals.valid_until',
            'trade_signals.source AS analytics_application',
        ];

        return 'SELECT '.implode(', ', $columns).' FROM trading_orders AS orders'
            .' JOIN trading_trade_signals AS trade_signals ON trade_signals.id = orders.trade_signal_id';
    }
};
