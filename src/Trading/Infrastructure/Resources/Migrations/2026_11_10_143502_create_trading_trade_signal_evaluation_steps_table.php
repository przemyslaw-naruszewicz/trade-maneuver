<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The evaluation steps each trade signal has passed, so that a rerun of its evaluation skips them.
        Schema::create('trading_trade_signal_evaluation_steps', function (Blueprint $table): void {
            $table->uuid('trade_signal_id');
            $table->text('step');
            $table->timestampTz('passed_at', 6);

            $table->primary(['trade_signal_id', 'step']);
            $table->foreign('trade_signal_id')->references('id')->on('trading_trade_signals');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_trade_signal_evaluation_steps');
    }
};
