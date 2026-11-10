<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // A trade signal leads to at most one order, also when two workers create it at the same time.
            $table->uuid('trade_signal_id')->unique();
            $table->uuid('investment_configuration_id');
            $table->text('status');
            $table->text('execution_progress');
            $table->text('direction');
            $table->text('instrument_symbol');
            $table->char('instrument_mic', 4);
            $table->decimal('limit_price', 19, 2);
            $table->bigInteger('quantity');

            // The snapshot of the investment configuration's order settings that the order used.
            $table->decimal('order_amount', 19, 2);
            $table->decimal('limit_price_tolerance', 5, 2);
            $table->char('currency', 3);
            $table->text('brokerage_account');

            $table->timestampTz('created_at', 6);

            $table->foreign('trade_signal_id')->references('id')->on('trading_trade_signals');
            $table->foreign('investment_configuration_id')->references('id')->on('trading_investment_configurations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_orders');
    }
};
