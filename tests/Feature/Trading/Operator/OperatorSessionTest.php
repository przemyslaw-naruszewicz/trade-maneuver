<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;

final class OperatorSessionTest extends TestCase
{
    use OperatorAccounts;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEnrolledOperator();
    }

    public function test_an_unauthenticated_request_is_redirected_to_login(): void
    {
        $this->get(route('panel.start'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_request_without_a_valid_csrf_token_is_refused(): void
    {
        $this->checkCsrfTokensAsOutsideTests();

        $this->post(route('login.store'), ['email' => self::OPERATOR_EMAIL, 'password' => self::OPERATOR_PASSWORD])
            ->assertStatus(419);
        $this->post(route('login.store'), [
            'email' => self::OPERATOR_EMAIL,
            'password' => self::OPERATOR_PASSWORD,
            '_token' => 'not-the-session-token',
        ])->assertStatus(419);
        $this->post(route('login.totp.store'), ['code' => $this->currentTotpCode()])->assertStatus(419);
        $this->assertGuest();

        $this->actingAs($this->operatorModel());
        $this->post(route('logout'))->assertStatus(419);
        $this->assertAuthenticatedAs($this->operatorModel());
    }

    public function test_a_request_with_the_session_csrf_token_is_accepted(): void
    {
        $this->checkCsrfTokensAsOutsideTests();
        $this->get(route('login'))->assertOk();

        $this->post(route('login.store'), [
            'email' => self::OPERATOR_EMAIL,
            'password' => self::OPERATOR_PASSWORD,
            '_token' => session()->token(),
        ])->assertRedirect(route('login.totp'));
    }

    public function test_logout_invalidates_the_session(): void
    {
        $this->actingAs($this->operatorModel())->withSession(['panel.marker' => 'kept until logout']);
        $this->get(route('panel.start'))->assertOk();
        $sessionBeforeLogout = session()->getId();

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotSame($sessionBeforeLogout, session()->getId());
        $this->assertFalse(session()->has('panel.marker'));
        $this->get(route('panel.start'))->assertRedirect(route('login'));
    }

    /**
     * The middleware skips the check while unit tests run; this keeps the check on.
     */
    private function checkCsrfTokensAsOutsideTests(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }
}
