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
        private LoggerInterface $logger,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (PendingTotpChallenge::operatorId($request->session()) === null) {
            return redirect()->route('login');
        }

        return view('trading.auth.totp-challenge');
    }

    public function store(SubmitOperatorTotpCodeRequest $request): RedirectResponse
    {
        $operatorId = PendingTotpChallenge::operatorId($request->session());
        if ($operatorId === null) {
            return redirect()->route('login');
        }

        try {
            $this->confirmTotpCode->handle(new ConfirmOperatorTotpCodeCommand($operatorId, $request->code()));
        } catch (InvalidTotpCodeException $exception) {
            $this->logger->warning('Operator login failed at the TOTP step.', [
                'operator' => $operatorId,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login.totp')->withErrors(['code' => $exception->getMessage()]);
        }

        PendingTotpChallenge::end($request->session());
        $this->auth->guard()->loginUsingId($operatorId);
        $request->session()->regenerate();

        return redirect()->intended(route('panel.start'));
    }
}
