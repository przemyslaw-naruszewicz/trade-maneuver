<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database session driver stores the ID of the logged-in operator, which is a UUID. PostgreSQL cannot cast a
     * bigint to a UUID, so the column is replaced; a session only loses the ID of the user it belonged to.
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
        Schema::table('sessions', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->index();
        });
    }
};
