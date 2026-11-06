<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_investment_configurations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('reference');
            $table->char('currency', 3);
            $table->text('brokerage_account');
            $table->decimal('order_amount', 19, 2);
            $table->decimal('limit_price_tolerance', 5, 2);
            $table->jsonb('authorized_analytics_applications');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_investment_configurations');
    }
};
