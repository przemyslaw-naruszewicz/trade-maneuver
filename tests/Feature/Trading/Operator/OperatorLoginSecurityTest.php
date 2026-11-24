<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;

final class OperatorLoginSecurityTest extends TestCase
{
    use OperatorAccounts;
    use RefreshDatabase;

    /** @var list<MessageLogged> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEnrolledOperator();
        Event::listen(MessageLogged::class, function (MessageLogged $message): void {
            $this->logged[] = $message;
        });
    }

    public function test_a_failed_password_attempt_is_logged_without_the_password(): void
    {
        $this->submitPassword('a-wrong-password');

        $this->assertLoggedWarning('Operator login failed at the password step.', ['email' => self::OPERATOR_EMAIL, 'ip' => '127.0.0.1']);
    }

    public function test_a_failed_code_is_logged_without_the_code(): void
    {
        $this->submitPassword();
        $this->submitTotpCode($this->wrongTotpCode());

        $this->assertLoggedWarning('Operator login failed at the TOTP step.', ['operator' => $this->operatorModel()->id, 'ip' => '127.0.0.1']);
    }

    public function test_a_blocked_password_attempt_is_logged(): void
    {
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->submitPassword('a-wrong-password');
        }

        $this->assertLoggedWarning(
            'Operator login blocked at the password step after too many failed attempts.',
            ['email' => self::OPERATOR_EMAIL, 'ip' => '127.0.0.1'],
        );
    }

    public function test_a_blocked_code_is_logged(): void
    {
        $this->submitPassword();
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->submitTotpCode($this->wrongTotpCode());
        }

        $this->assertLoggedWarning(
            'Operator login blocked at the TOTP step after too many failed attempts.',
            ['operator' => $this->operatorModel()->id, 'ip' => '127.0.0.1'],
        );
    }

    public function test_each_login_step_starts_a_new_session_and_the_login_ends_the_totp_challenge(): void
    {
        // Without the session cookie each test request would start a new session, so it is sent like a browser does.
        $this->get(route('login'))->assertOk();
        $sessionBeforePassword = session()->getId();

        $this->withSessionCookie($sessionBeforePassword)->submitPassword()->assertRedirect(route('login.totp'));
        $sessionAfterPassword = session()->getId();
        $this->withSessionCookie($sessionAfterPassword)->submitTotpCode($this->currentTotpCode())->assertRedirect(route('panel.start'));

        $this->assertNotSame($sessionBeforePassword, $sessionAfterPassword);
        $this->assertNotSame($sessionAfterPassword, session()->getId());
        $this->assertFalse(session()->has('trading.operator.pending_totp_challenge'));
    }

    public function test_the_e_mail_address_matches_whatever_its_case_and_surrounding_spaces(): void
    {
        $this->submitPassword(email: '  Operator@Example.TEST ')->assertRedirect(route('login.totp'));
    }

    public function test_a_code_with_spaces_between_its_digits_is_accepted(): void
    {
        $code = $this->currentTotpCode();
        $this->submitPassword();

        $this->submitTotpCode(substr($code, 0, 3).' '.substr($code, 3))->assertRedirect(route('panel.start'));
    }

    public function test_a_correct_password_clears_the_failed_password_attempts(): void
    {
        for ($round = 1; $round <= 2; $round++) {
            for ($attempt = 1; $attempt <= 4; $attempt++) {
                $this->submitPassword('a-wrong-password');
            }
            $this->submitPassword()->assertRedirect(route('login.totp'));
        }
    }

    public function test_a_correct_code_clears_the_failed_code_attempts(): void
    {
        $google2fa = new Google2FA;
        $now = $google2fa->getTimestamp();

        foreach ([$now - 1, $now] as $timestep) {
            $this->submitPassword();
            for ($attempt = 1; $attempt <= 4; $attempt++) {
                $this->submitTotpCode($this->wrongTotpCode());
            }
            $this->submitTotpCode($google2fa->oathTotp($this->totpSecret, $timestep))->assertRedirect(route('panel.start'));
            $this->post(route('logout'));
        }
    }

    public function test_logout_issues_a_new_csrf_token(): void
    {
        $this->actingAs($this->operatorModel())->get(route('panel.start'))->assertOk();
        $tokenBeforeLogout = session()->token();

        $this->post(route('logout'));

        $tokenAfterLogout = session()->get('_token');
        $this->assertIsString($tokenAfterLogout);
        $this->assertNotSame($tokenBeforeLogout, $tokenAfterLogout);
    }

    private function withSessionCookie(string $sessionId): self
    {
        $cookie = config('session.cookie');
        $this->assertIsString($cookie);

        return $this->withCookie($cookie, $sessionId);
    }

    /**
     * @param  array<string, string>  $context
     */
    private function assertLoggedWarning(string $message, array $context): void
    {
        $matching = [];
        foreach ($this->logged as $logged) {
            if ($logged->message === $message) {
                $matching[] = $logged;
            }
        }

        $this->assertNotEmpty($matching, sprintf('"%s" was not logged.', $message));
        $this->assertSame('warning', $matching[0]->level);
        $this->assertSame($context, $matching[0]->context);
    }
}
