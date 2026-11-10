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
        config(['database.connections.'.config('database.default').'.database' => $database]);
        $provider = $this->app->getProvider(AppServiceProvider::class);
        $this->assertInstanceOf(AppServiceProvider::class, $provider);
        $provider->boot();
    }
}
