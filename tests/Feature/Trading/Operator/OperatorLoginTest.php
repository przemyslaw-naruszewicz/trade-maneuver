<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;

final class OperatorLoginTest extends TestCase
{
    use OperatorAccounts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEnrolledOperator();
    }

    public function test_a_correct_password_and_code_log_the_operator_in_with_a_new_session(): void
    {
        $this->get(route('login'))->assertOk();
        $sessionBeforeLogin = session()->getId();

        $this->submitPassword()->assertRedirect(route('login.totp'));
        $this->assertGuest();
        $this->get(route('login.totp'))->assertOk();

        $this->submitTotpCode($this->currentTotpCode())->assertRedirect(route('panel.start'));

        $this->assertAuthenticatedAs($this->operatorModel());
        $this->assertNotSame($sessionBeforeLogin, session()->getId());
        $this->get(route('panel.start'))->assertOk();
    }

    public function test_a_wrong_password_is_refused_without_a_totp_challenge(): void
    {
        $this->from(route('login'))->submitPassword('a-wrong-password')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'These credentials do not match the operator account.']);

        $this->assertGuest();
        $this->get(route('login.totp'))->assertRedirect(route('login'));
    }

    public function test_a_wrong_code_is_refused(): void
    {
        $this->submitPassword()->assertRedirect(route('login.totp'));

        $this->from(route('login.totp'))->submitTotpCode($this->wrongTotpCode())
            ->assertRedirect(route('login.totp'))
            ->assertSessionHasErrors(['code' => 'This code is not valid, or it has already been used.']);

        $this->assertGuest();
        $this->get(route('panel.start'))->assertRedirect(route('login'));
    }
}
