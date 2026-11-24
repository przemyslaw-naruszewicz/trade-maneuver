<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class OperatorAccountCommandsTest extends TestCase
{
    use RefreshDatabase;

    private const string EMAIL = 'operator@example.test';

    private const string PASSWORD = 'a-local-operator-password';

    public function test_the_operator_account_is_created_with_a_hashed_password(): void
    {
        $this->createOperator(self::EMAIL, self::PASSWORD)
            ->expectsOutputToContain('The operator account was created.')
            ->assertSuccessful();

        $this->assertDatabaseCount('trading_operators', 1);
        $this->assertSame(self::EMAIL, DB::table('trading_operators')->value('email'));
        $storedPassword = $this->storedPassword();
        $this->assertNotSame(self::PASSWORD, $storedPassword);
        $this->assertTrue(Hash::check(self::PASSWORD, $storedPassword));
    }

    public function test_a_second_operator_account_is_refused(): void
    {
        $this->createOperator(self::EMAIL, self::PASSWORD)->assertSuccessful();

        $this->createOperator('second-operator@example.test', self::PASSWORD)
            ->expectsOutputToContain('The operator account already exists.')
            ->assertFailed();

        $this->assertDatabaseCount('trading_operators', 1);
        $this->assertSame(self::EMAIL, DB::table('trading_operators')->value('email'));
    }

    public function test_a_password_reset_changes_the_password(): void
    {
        $this->createOperator(self::EMAIL, self::PASSWORD)->assertSuccessful();

        $this->resetPassword(self::EMAIL, 'a-new-local-operator-password')
            ->expectsOutputToContain('The operator password was reset.')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('a-new-local-operator-password', $this->storedPassword()));
        $this->assertFalse(Hash::check(self::PASSWORD, $this->storedPassword()));
    }

    public function test_a_password_reset_for_an_unknown_e_mail_address_changes_nothing(): void
    {
        $this->createOperator(self::EMAIL, self::PASSWORD)->assertSuccessful();

        $this->resetPassword('someone-else@example.test', 'a-new-local-operator-password')
            ->expectsOutputToContain('No operator account has the e-mail address "someone-else@example.test".')
            ->assertFailed();

        $this->assertTrue(Hash::check(self::PASSWORD, $this->storedPassword()));
    }

    public function test_totp_enrolment_prints_a_secret_and_an_otpauth_uri_and_stores_the_secret_encrypted(): void
    {
        $this->createOperator(self::EMAIL, self::PASSWORD)->assertSuccessful();

        $output = $this->enrolTotp(self::EMAIL);
        $this->assertSame(1, preg_match('/^Secret: ([A-Z2-7]{32})$/m', $output, $secretMatch), $output);
        $secret = $secretMatch[1];
        $this->assertStringContainsString(
            'otpauth://totp/Trade%20Maneuver:operator%40example.test?secret='.$secret.'&issuer=Trade%20Maneuver',
            $output,
        );
        $this->assertStringContainsString('&digits=6&period=30', $output);

        $storedSecret = (string) DB::table('trading_operators')->value('totp_secret');
        $this->assertStringNotContainsString($secret, $storedSecret);
        $this->assertSame($secret, Crypt::decryptString($storedSecret));
    }

    public function test_a_new_totp_enrolment_replaces_the_secret(): void
    {
        $this->createOperator(self::EMAIL, self::PASSWORD)->assertSuccessful();
        $this->enrolTotp(self::EMAIL);
        $firstSecret = $this->storedTotpSecret();

        $output = $this->enrolTotp(self::EMAIL);

        $secondSecret = $this->storedTotpSecret();
        $this->assertNotSame($firstSecret, $secondSecret);
        $this->assertStringContainsString('Secret: '.$secondSecret, $output);
    }

    private function createOperator(string $email, string $password): PendingCommand
    {
        $command = $this->artisan('trading:create-operator', ['email' => $email]);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm the password', $password);
    }

    private function resetPassword(string $email, string $password): PendingCommand
    {
        $command = $this->artisan('trading:reset-operator-password', ['email' => $email]);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm the password', $password);
    }

    private function enrolTotp(string $email): string
    {
        $this->assertSame(0, Artisan::call('trading:enrol-operator-totp', ['email' => $email]));

        return Artisan::output();
    }

    private function storedTotpSecret(): string
    {
        $storedSecret = DB::table('trading_operators')->value('totp_secret');
        $this->assertIsString($storedSecret);

        return Crypt::decryptString($storedSecret);
    }

    private function storedPassword(): string
    {
        return (string) DB::table('trading_operators')->value('password');
    }
}
