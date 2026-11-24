<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Timebox;
use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Ui\Http\Request\SubmitOperatorPasswordRequest;

/**
 * The password step. A correct password leads only to the TOTP challenge; it does not authenticate the session.
 */
final readonly class OperatorSignInController
{
    // Answers take the same time whether or not the e-mail address belongs to the operator account.
    private const int MINIMUM_ANSWER_MICROSECONDS = 250_000;

    public function __construct(
        private AuthManager $auth,
        private Timebox $timebox,
        private OperatorLoginThrottle $throttle,
        private LoggerInterface $logger,
    ) {}

    public function create(): View
    {
        return view('trading.auth.sign-in');
    }

    public function store(SubmitOperatorPasswordRequest $request): RedirectResponse
    {
        if ($this->throttle->isBlocked($request->email())) {
            $this->logger->warning('Operator login blocked at the password step after too many failed attempts.', [
                'email' => $request->email(),
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')
                ->withErrors(['email' => 'Too many failed attempts. Try again in a minute.'])
                ->onlyInput('email');
        }

        $operator = $this->timebox->call(
            fn (): ?Authenticatable => $this->operatorWithPassword($request->email(), $request->password()),
            self::MINIMUM_ANSWER_MICROSECONDS,
        );

        if ($operator === null) {
            $this->throttle->recordFailure($request->email());
            $this->logger->warning('Operator login failed at the password step.', [
                'email' => $request->email(),
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')
                ->withErrors(['email' => 'These credentials do not match the operator account.'])
                ->onlyInput('email');
        }

        $this->throttle->clear($request->email());
        $request->session()->regenerate();
        PendingTotpChallenge::start($request->session(), (string) $operator->getAuthIdentifier(), $request->email());

        return redirect()->route('login.totp');
    }

    private function operatorWithPassword(string $email, #[\SensitiveParameter] string $password): ?Authenticatable
    {
        $operators = $this->auth->createUserProvider('operators');
        if ($operators === null) {
            return null;
        }
        $operator = $operators->retrieveByCredentials(['email' => $email]);

        return $operator !== null && $operators->validateCredentials($operator, ['password' => $password]) ? $operator : null;
    }
}
