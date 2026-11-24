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

    private function createOperator(string $email, string $password): PendingCommand
    {
        $command = $this->artisan('trading:create-operator', ['email' => $email]);
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
