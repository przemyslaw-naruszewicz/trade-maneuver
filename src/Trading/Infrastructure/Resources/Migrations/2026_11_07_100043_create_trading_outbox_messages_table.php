<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_outbox_messages', function (Blueprint $table): void {
            $table->id();
            // Not unique: republishing a stored signal outcome records the same message again.
            $table->uuid('message_id');
            $table->text('message_type');
            $table->text('recipient');
            $table->jsonb('payload');
            $table->timestampTz('recorded_at', 6);
            $table->timestampTz('sent_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_outbox_messages');
    }
};
