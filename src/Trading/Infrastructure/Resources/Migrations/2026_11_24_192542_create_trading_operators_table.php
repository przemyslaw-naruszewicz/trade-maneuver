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
        Schema::create('trading_operators', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('email')->unique();
            $table->text('password');
            // Encrypted with the application key; the column never holds the plain secret.
            $table->text('totp_secret')->nullable();
            $table->bigInteger('last_accepted_totp_timestep')->nullable();
        });

        // There is one operator account: every row shares the indexed value.
        DB::statement('CREATE UNIQUE INDEX trading_operators_single_row ON trading_operators ((true))');
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_operators');
    }
};
