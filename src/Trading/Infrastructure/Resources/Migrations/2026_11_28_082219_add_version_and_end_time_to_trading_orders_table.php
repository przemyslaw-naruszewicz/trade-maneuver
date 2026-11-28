<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_orders', function (Blueprint $table): void {
            // The optimistic version check of the operator's decisions: each save increments it.
            $table->bigInteger('version')->default(1);
            // When the order reached a terminal status.
            $table->timestampTz('ended_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trading_orders', function (Blueprint $table): void {
            $table->dropColumn(['version', 'ended_at']);
        });
    }
};
