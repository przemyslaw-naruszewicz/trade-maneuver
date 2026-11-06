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
        Schema::create('trading_investment_configurations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('reference')->unique();
            $table->char('currency', 3);
            $table->text('brokerage_account');
            $table->decimal('order_amount', 19, 2);
            $table->decimal('limit_price_tolerance', 5, 2);
            $table->jsonb('authorized_analytics_applications');
        });

        // This iteration has exactly one investment configuration: every row shares the indexed value.
        DB::statement('CREATE UNIQUE INDEX trading_investment_configurations_single_row ON trading_investment_configurations ((true))');
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_investment_configurations');
    }
};
