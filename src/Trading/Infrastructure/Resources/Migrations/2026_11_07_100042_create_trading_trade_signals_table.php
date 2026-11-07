<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_trade_signals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('source');
            $table->timestampTz('received_at', 6);

            // The content of a valid trade signal; a rejected one has none. Unscaled numeric keeps decimals as sent.
            $table->timestampTz('issued_at', 6)->nullable();
            $table->text('investment_configuration_ref')->nullable();
            $table->text('instrument_symbol')->nullable();
            $table->char('instrument_mic', 4)->nullable();
            $table->text('direction')->nullable();
            $table->rawColumn('confidence', 'numeric')->nullable();
            $table->integer('horizon_sessions')->nullable();
            $table->date('horizon_at')->nullable();
            $table->rawColumn('expected_move_min', 'numeric')->nullable();
            $table->rawColumn('expected_move_max', 'numeric')->nullable();
            $table->rawColumn('reference_price_amount', 'numeric')->nullable();
            $table->char('reference_price_currency', 3)->nullable();
            $table->timestampTz('reference_price_as_of', 6)->nullable();
            $table->timestampTz('valid_until', 6)->nullable();

            $table->uuid('outcome_id')->nullable();
            $table->text('outcome')->nullable();
            $table->timestampTz('outcome_occurred_at', 6)->nullable();
            $table->jsonb('outcome_violations')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE trading_trade_signals ADD CONSTRAINT trading_trade_signals_content_or_outcome
                CHECK (issued_at IS NOT NULL OR outcome IS NOT NULL)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_trade_signals');
    }
};
