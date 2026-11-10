<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_trade_signals', function (Blueprint $table): void {
            // The reason code of a signal decline; a signal rejection has violations instead.
            $table->text('outcome_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trading_trade_signals', function (Blueprint $table): void {
            $table->dropColumn('outcome_reason');
        });
    }
};
