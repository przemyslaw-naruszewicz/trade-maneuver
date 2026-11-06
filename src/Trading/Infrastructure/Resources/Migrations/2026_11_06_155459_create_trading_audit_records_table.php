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
        Schema::create('trading_audit_records', function (Blueprint $table): void {
            $table->id();
            $table->text('actor');
            $table->timestampTz('occurred_at', 6);
            $table->text('entity_type');
            $table->text('entity_id');
            $table->text('action');
            $table->jsonb('values');
        });

        DB::unprepared(<<<'SQL'
            CREATE FUNCTION trading_audit_records_reject_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'The audit trail is append-only.';
            END
            $$;
            CREATE TRIGGER trading_audit_records_append_only BEFORE UPDATE OR DELETE ON trading_audit_records
                FOR EACH ROW EXECUTE FUNCTION trading_audit_records_reject_change();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_audit_records');
        DB::unprepared('DROP FUNCTION IF EXISTS trading_audit_records_reject_change()');
    }
};
