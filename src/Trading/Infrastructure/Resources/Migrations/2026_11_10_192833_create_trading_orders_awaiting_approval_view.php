<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The read side of orders awaiting approval, with the trade signal of each. A view that joins two tables is not
     * updatable, so PostgreSQL refuses every write through it (ADR-0004).
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW trading_orders_awaiting_approval AS
            SELECT
                orders.id,
                orders.status,
                orders.limit_price,
                orders.currency,
                orders.quantity,
                orders.order_amount,
                orders.execution_progress,
                orders.trade_signal_id,
                trade_signals.instrument_symbol,
                trade_signals.instrument_mic,
                trade_signals.direction,
                trade_signals.confidence,
                trade_signals.horizon_sessions,
                trade_signals.horizon_at,
                trade_signals.expected_move_min,
                trade_signals.expected_move_max,
                trade_signals.reference_price_amount,
                trade_signals.reference_price_currency,
                trade_signals.reference_price_as_of,
                trade_signals.valid_until,
                trade_signals.source AS analytics_application
            FROM trading_orders AS orders
            JOIN trading_trade_signals AS trade_signals ON trade_signals.id = orders.trade_signal_id
            WHERE orders.status = 'awaiting_approval'
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS trading_orders_awaiting_approval');
    }
};
