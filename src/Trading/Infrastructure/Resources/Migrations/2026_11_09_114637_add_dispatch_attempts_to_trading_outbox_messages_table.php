<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_outbox_messages', function (Blueprint $table): void {
            $table->unsignedInteger('attempts')->default(0);
            // Null until a failed attempt sets when the dispatcher may try again.
            $table->timestampTz('next_attempt_at', 6)->nullable();
            $table->text('last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trading_outbox_messages', function (Blueprint $table): void {
            $table->dropColumn(['attempts', 'next_attempt_at', 'last_error']);
        });
    }
};
