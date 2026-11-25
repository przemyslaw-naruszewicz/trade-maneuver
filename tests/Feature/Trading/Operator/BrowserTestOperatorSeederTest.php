<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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
}
