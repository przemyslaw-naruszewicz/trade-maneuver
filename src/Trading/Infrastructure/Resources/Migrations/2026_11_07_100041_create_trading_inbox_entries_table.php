<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_inbox_entries', function (Blueprint $table): void {
            $table->text('consumer');
            $table->uuid('message_id');
            // SHA-256 of the message body, which tells a duplicate from a conflicting reuse of the message id.
            $table->char('fingerprint', 64);
            $table->timestampTz('received_at', 6);
            $table->primary(['consumer', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_inbox_entries');
    }
};
