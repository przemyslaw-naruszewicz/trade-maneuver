<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use TradeManeuver\Trading\Application\Operator\Command\ConfirmOperatorTotpCode\ConfirmOperatorTotpCodeCommand;
use TradeManeuver\Trading\Application\Operator\Command\ConfirmOperatorTotpCode\ConfirmOperatorTotpCodeCommandHandler;
use TradeManeuver\Trading\Application\Operator\Exception\InvalidTotpCodeException;
use TradeManeuver\Trading\Ui\Http\Request\SubmitOperatorTotpCodeRequest;

/**
 * The TOTP step. Only a valid code authenticates the session.
 */
final readonly class OperatorTotpChallengeController
{
    public function __construct(
        private AuthManager $auth,
        private ConfirmOperatorTotpCodeCommandHandler $confirmTotpCode,
        private OperatorLoginThrottle $throttle,
        private LoggerInterface $logger,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (PendingTotpChallenge::of($request->session()) === null) {
            return redirect()->route('login');
        }

        return view('trading.auth.totp-challenge');
    }

    public function store(SubmitOperatorTotpCodeRequest $request): RedirectResponse
    {
        $challenge = PendingTotpChallenge::of($request->session());
        if ($challenge === null) {
            return redirect()->route('login');
        }

        if (! $this->throttle->admitAttempt(OperatorLoginStep::Totp, $challenge->email)) {
            $this->logger->warning('Operator login blocked at the TOTP step after too many failed attempts.', [
                'operator' => $challenge->operatorId,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login.totp')->withErrors(['code' => 'Too many failed attempts. Try again in a minute.']);
        }

        try {
            $this->confirmTotpCode->handle(new ConfirmOperatorTotpCodeCommand($challenge->operatorId, $request->code()));
        } catch (InvalidTotpCodeException $exception) {
            $this->logger->warning('Operator login failed at the TOTP step.', [
                'operator' => $challenge->operatorId,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login.totp')->withErrors(['code' => $exception->getMessage()]);
        }

        $this->throttle->clear(OperatorLoginStep::Totp, $challenge->email);
        PendingTotpChallenge::end($request->session());
        // Logging in moves the session to a new identifier.
        $this->auth->guard()->loginUsingId($challenge->operatorId);

        return redirect()->intended(route('panel.start'));
    }
}
