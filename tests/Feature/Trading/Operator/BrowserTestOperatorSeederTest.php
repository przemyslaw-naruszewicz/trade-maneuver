<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use TradeManeuver\Trading\Infrastructure\Operator\BrowserTestOperatorSeeder;

final class BrowserTestOperatorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_the_one_operator_account_with_the_browser_test_e_mail_address_and_totp_secret(): void
    {
        $this->seed(BrowserTestOperatorSeeder::class);

        $this->assertDatabaseCount('trading_operators', 1);
        $operator = DB::table('trading_operators')->first();
        $this->assertNotNull($operator);
        $this->assertSame('operator@example.test', $operator->email);
        $this->assertIsString($operator->totp_secret);
        $this->assertSame('DEMODEMODEMODEMODEMODEMODEMODEMO', Crypt::decryptString($operator->totp_secret));
    }

    /**
     * Databases that do not exist, so that a seeder without the refusal cannot write to a real one.
     *
     * @return iterable<string, array{array<string, string>}>
     */
    public static function otherDatabases(): iterable
    {
        yield 'database name' => [['database' => 'trade_maneuver_other']];
        yield 'database URL' => [['url' => 'pgsql://trade_maneuver@postgres:5432/trade_maneuver_other']];
    }

    /**
     * @param  array<string, string>  $connectionSettings
     */
    #[DataProvider('otherDatabases')]
    public function test_the_seeder_refuses_a_database_other_than_the_browser_test_and_test_databases(
        array $connectionSettings,
    ): void {
        $refusal = $this->seedWithConnectionSettings($connectionSettings);

        $this->assertSame(
            'The browser-test operator account is seeded only into trade_maneuver_e2e or trade_maneuver_testing.',
            $refusal,
        );
        $this->assertDatabaseCount('trading_operators', 0);
    }

    /**
     * Seeds through a new connection with the settings, then returns to the test database.
     *
     * @param  array<string, string>  $connectionSettings
     * @return string|null the refusal, or null when the seeder ran
     */
    private function seedWithConnectionSettings(array $connectionSettings): ?string
    {
        $connection = 'database.connections.'.config('database.default');
        $testSettings = config($connection);
        config([$connection => [...$testSettings, ...$connectionSettings]]);
        DB::purge();

        try {
            $this->seed(BrowserTestOperatorSeeder::class);

            return null;
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        } finally {
            config([$connection => $testSettings]);
            DB::purge();
        }
    }
}
