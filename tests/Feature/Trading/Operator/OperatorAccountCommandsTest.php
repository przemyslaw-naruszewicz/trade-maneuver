<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function storedPassword(): string
    {
        return (string) DB::table('trading_operators')->value('password');
    }
}
