<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Laravel's destructive database commands run only against the test database. The commands are checked with
 * `--pretend`, so a missing guard dumps SQL instead of changing a database.
 */
final class DestructiveDatabaseCommandsTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::prohibitDestructiveCommands(false);

        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function destructiveCommands(): iterable
    {
        yield 'rollback' => ['migrate:rollback'];
        yield 'reset' => ['migrate:reset'];
    }

    #[DataProvider('destructiveCommands')]
    public function test_a_destructive_command_is_refused_on_a_database_other_than_the_test_database(string $command): void
    {
        $this->bootWithDatabase('trade_maneuver');

        $this->pretend($command)
            ->expectsOutputToContain('This command is prohibited from running in this environment.')
            ->assertFailed();
    }

    /**
     * The URL names PostgreSQL's maintenance database, which holds no project data, so a missing refusal changes
     * nothing: the command runs with `--pretend`.
     */
    public function test_a_destructive_command_is_refused_on_another_database_named_by_the_database_url(): void
    {
        $this->bootWithConnectionSettings([
            'database' => 'trade_maneuver_testing',
            'url' => 'pgsql://trade_maneuver@postgres:5432/postgres',
        ]);

        $this->pretend('migrate:reset')
            ->expectsOutputToContain('This command is prohibited from running in this environment.')
            ->assertFailed();
    }

    #[DataProvider('destructiveCommands')]
    public function test_a_destructive_command_runs_on_the_test_database(string $command): void
    {
        $this->bootWithDatabase('trade_maneuver_testing');

        $this->pretend($command)
            ->doesntExpectOutputToContain('This command is prohibited from running in this environment.')
            ->assertSuccessful();
    }

    private function pretend(string $command): PendingCommand
    {
        $pendingCommand = $this->artisan($command, ['--pretend' => true]);
        $this->assertInstanceOf(PendingCommand::class, $pendingCommand);

        return $pendingCommand;
    }

    private function bootWithDatabase(string $database): void
    {
        $this->bootWithConnectionSettings(['database' => $database]);
    }

    /**
     * @param  array<string, string>  $connectionSettings
     */
    private function bootWithConnectionSettings(array $connectionSettings): void
    {
        $connection = 'database.connections.'.config('database.default');
        config([$connection => [...config($connection), ...$connectionSettings]]);
        DB::purge();
        $provider = $this->app->getProvider(AppServiceProvider::class);
        $this->assertInstanceOf(AppServiceProvider::class, $provider);
        $provider->boot();
    }
}
