<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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

    public function test_the_seeder_refuses_a_database_other_than_the_browser_test_and_test_databases(): void
    {
        config(['database.connections.'.config('database.default').'.database' => 'trade_maneuver']);

        $refusal = null;
        try {
            $this->seed(BrowserTestOperatorSeeder::class);
        } catch (RuntimeException $exception) {
            $refusal = $exception->getMessage();
        }

        $this->assertSame(
            'The browser-test operator account is seeded only into trade_maneuver_e2e or trade_maneuver_testing.',
            $refusal,
        );
        $this->assertDatabaseCount('trading_operators', 0);
    }
}
