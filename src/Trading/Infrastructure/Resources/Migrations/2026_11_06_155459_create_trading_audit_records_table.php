<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_audit_records', function (Blueprint $table): void {
            $table->id();
            $table->text('actor');
            $table->timestampTz('occurred_at', 6);
            $table->text('entity_type');
            $table->text('entity_id');
            $table->text('action');
            $table->jsonb('values');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_audit_records');
    }
};
