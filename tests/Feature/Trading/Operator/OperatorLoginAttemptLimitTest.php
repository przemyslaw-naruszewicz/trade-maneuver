<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;

final class OperatorLoginAttemptLimitTest extends TestCase
{
    use OperatorAccounts;
    use RefreshDatabase;

    private const string WRONG_PASSWORD = 'These credentials do not match the operator account.';

    private const string WRONG_CODE = 'This code is not valid, or it has already been used.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEnrolledOperator();
    }

    public function test_the_fifth_failed_password_attempt_within_a_minute_is_checked_and_the_sixth_attempt_is_blocked(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->submitPassword('a-wrong-password')->assertSessionHasErrors(['email' => self::WRONG_PASSWORD]);
        }

        $this->submitPassword()->assertRedirect(route('login'))->assertSessionHasErrors(['email' => $this->blockedMessage()]);
        $this->get(route('login.totp'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->submitPassword()->assertRedirect(route('login.totp'));
    }

    public function test_the_password_attempt_after_four_failures_is_still_checked(): void
    {
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->submitPassword('a-wrong-password')->assertSessionHasErrors(['email' => self::WRONG_PASSWORD]);
        }

        $this->submitPassword()->assertRedirect(route('login.totp'));
    }

    public function test_the_limit_applies_to_the_account_whatever_the_case_of_its_e_mail_address(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->submitPassword('a-wrong-password', strtoupper(self::OPERATOR_EMAIL));
        }

        $this->submitPassword()->assertSessionHasErrors(['email' => $this->blockedMessage()]);
    }

    public function test_the_fifth_failed_code_within_a_minute_is_checked_and_the_sixth_attempt_is_blocked(): void
    {
        $this->submitPassword()->assertRedirect(route('login.totp'));
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->submitTotpCode($this->wrongTotpCode())->assertSessionHasErrors(['code' => self::WRONG_CODE]);
        }

        $this->submitTotpCode($this->currentTotpCode())
            ->assertRedirect(route('login.totp'))
            ->assertSessionHasErrors(['code' => $this->blockedMessage()]);
        $this->assertGuest();

        $this->travel(61)->seconds();
        $this->submitTotpCode($this->currentTotpCode())->assertRedirect(route('panel.start'));
        $this->assertAuthenticated();
    }

    public function test_a_blocked_password_step_does_not_block_the_totp_step(): void
    {
        $this->submitPassword()->assertRedirect(route('login.totp'));
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->submitPassword('a-wrong-password');
        }
        $this->submitPassword()->assertSessionHasErrors(['email' => $this->blockedMessage()]);

        $this->submitTotpCode($this->wrongTotpCode())->assertSessionHasErrors(['code' => self::WRONG_CODE]);
        $this->submitTotpCode($this->currentTotpCode())->assertRedirect(route('panel.start'));
        $this->assertAuthenticated();
    }

    public function test_a_blocked_totp_step_does_not_block_the_password_step(): void
    {
        $this->submitPassword()->assertRedirect(route('login.totp'));
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->submitTotpCode($this->wrongTotpCode());
        }
        $this->submitTotpCode($this->currentTotpCode())->assertSessionHasErrors(['code' => $this->blockedMessage()]);

        $this->submitPassword('a-wrong-password')->assertSessionHasErrors(['email' => self::WRONG_PASSWORD]);
        $this->submitPassword()->assertRedirect(route('login.totp'));
        $this->assertGuest();
    }

    private function blockedMessage(): string
    {
        return 'Too many failed attempts. Try again in a minute.';
    }
}
