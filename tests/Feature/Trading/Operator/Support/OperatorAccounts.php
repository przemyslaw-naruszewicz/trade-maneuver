<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator\Support;

use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;
use TradeManeuver\Trading\Application\Operator\Command\CreateOperator\CreateOperatorCommand;
use TradeManeuver\Trading\Application\Operator\Command\CreateOperator\CreateOperatorCommandHandler;
use TradeManeuver\Trading\Application\Operator\Command\EnrolOperatorTotp\EnrolOperatorTotpCommand;
use TradeManeuver\Trading\Application\Operator\Command\EnrolOperatorTotp\EnrolOperatorTotpCommandHandler;
use TradeManeuver\Trading\Infrastructure\Operator\Eloquent\OperatorModel;

/**
 * Creates the operator account through its use cases, as the console commands do, for HTTP tests.
 */
trait OperatorAccounts
{
    private const string OPERATOR_EMAIL = 'operator@example.test';

    private const string OPERATOR_PASSWORD = 'a-local-operator-password';

    private string $totpSecret = '';

    /**
     * Skips the wait that keeps password answers equally long, so that tests with many attempts stay fast.
     */
    protected function setUpOperatorAccounts(): void
    {
        Sleep::fake();
    }

    private function createEnrolledOperator(): void
    {
        $this->app->make(CreateOperatorCommandHandler::class)
            ->handle(new CreateOperatorCommand(self::OPERATOR_EMAIL, self::OPERATOR_PASSWORD));
        $this->totpSecret = $this->app->make(EnrolOperatorTotpCommandHandler::class)
            ->handle(new EnrolOperatorTotpCommand(self::OPERATOR_EMAIL))
            ->secret;
    }

    private function currentTotpCode(): string
    {
        return (new Google2FA)->getCurrentOtp($this->totpSecret);
    }

    /**
     * A 6-digit code that matches no time step within the tolerance around now.
     */
    private function wrongTotpCode(): string
    {
        $google2fa = new Google2FA;
        $timestep = $google2fa->getTimestamp();
        $validCodes = [];
        foreach ([$timestep - 1, $timestep, $timestep + 1] as $candidate) {
            $validCodes[] = $google2fa->oathTotp($this->totpSecret, $candidate);
        }

        for ($number = 0; ; $number++) {
            $code = str_pad((string) $number, 6, '0', STR_PAD_LEFT);
            if (! in_array($code, $validCodes, true)) {
                return $code;
            }
        }
    }

    private function operatorModel(): OperatorModel
    {
        return OperatorModel::query()->where('email', self::OPERATOR_EMAIL)->firstOrFail();
    }

    /**
     * @return TestResponse<Response>
     */
    private function submitPassword(string $password = self::OPERATOR_PASSWORD, string $email = self::OPERATOR_EMAIL): TestResponse
    {
        return $this->post(route('login.store'), ['email' => $email, 'password' => $password]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function submitTotpCode(string $code): TestResponse
    {
        return $this->post(route('login.totp.store'), ['code' => $code]);
    }
}
