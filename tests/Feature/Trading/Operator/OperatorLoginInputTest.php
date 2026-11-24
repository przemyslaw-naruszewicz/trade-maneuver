<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;

final class OperatorLoginInputTest extends TestCase
{
    use OperatorAccounts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEnrolledOperator();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidPasswordStepInput(): iterable
    {
        yield 'no e-mail address' => [['password' => 'a-password'], 'email', 'The email field is required.'];
        yield 'an e-mail address that is not a string' => [['email' => ['a'], 'password' => 'a-password'], 'email', 'The email field must be a string.'];
        yield 'an e-mail address over 254 characters' => [
            ['email' => str_repeat('a', 243).'@example.test', 'password' => 'a-password'],
            'email',
            'The email field must not be greater than 254 characters.',
        ];
        yield 'no password' => [['email' => 'operator@example.test'], 'password', 'The password field is required.'];
        yield 'a password that is not a string' => [['email' => 'operator@example.test', 'password' => ['a']], 'password', 'The password field must be a string.'];
        yield 'a password over 4096 characters' => [
            ['email' => 'operator@example.test', 'password' => str_repeat('a', 4097)],
            'password',
            'The password field must not be greater than 4096 characters.',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidPasswordStepInput')]
    public function test_invalid_password_step_input_is_refused(array $input, string $field, string $error): void
    {
        $this->from(route('login'))->post(route('login.store'), $input)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([$field => $error]);

        $this->assertGuest();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidTotpStepInput(): iterable
    {
        yield 'no code' => [[], 'The code field is required.'];
        yield 'a code that is not a string' => [['code' => ['123456']], 'The code field must be a string.'];
        yield 'a code over 6 characters' => [['code' => str_repeat('1', 7)], 'The code field must not be greater than 6 characters.'];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidTotpStepInput')]
    public function test_invalid_totp_step_input_is_refused(array $input, string $error): void
    {
        $this->submitPassword();

        $this->from(route('login.totp'))->post(route('login.totp.store'), $input)
            ->assertRedirect(route('login.totp'))
            ->assertSessionHasErrors(['code' => $error]);

        $this->assertGuest();
    }
}
