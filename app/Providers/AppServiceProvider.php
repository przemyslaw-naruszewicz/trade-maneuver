<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
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
        // The connection's configuration includes the database that DB_URL names.
        $database = DB::connection()->getConfig('database');
        DB::prohibitDestructiveCommands($database !== 'trade_maneuver_testing');

        $viteHotFile = config('app.vite_hot_file');
        if (is_string($viteHotFile) && $viteHotFile !== '') {
            Vite::useHotFile($viteHotFile);
        }
    }
}
