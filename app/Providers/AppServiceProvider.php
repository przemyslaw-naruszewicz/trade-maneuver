<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // migrate:fresh, migrate:refresh, migrate:reset, migrate:rollback and db:wipe run only against the test database.
        $database = config('database.connections.'.config('database.default').'.database');
        DB::prohibitDestructiveCommands($database !== 'trade_maneuver_testing');
    }
}
