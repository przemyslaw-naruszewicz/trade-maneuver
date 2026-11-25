<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Operator;

use Illuminate\Database\Seeder;
use TradeManeuver\Trading\Application\Operator\Command\CreateOperator\CreateOperatorCommand;
use TradeManeuver\Trading\Application\Operator\Command\CreateOperator\CreateOperatorCommandHandler;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\Operator\Repository\OperatorRepositoryInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpSecret;

/**
 * Creates the operator account that the browser tests log in with. bin/e2e runs it on the e2e database only. The
 * browser tests compute TOTP codes from the fixed secret.
 */
final class BrowserTestOperatorSeeder extends Seeder
{
    private const string EMAIL = 'operator@example.test';

    private const string PASSWORD = 'a-browser-test-operator-password';

    private const string TOTP_SECRET = 'DEMODEMODEMODEMODEMODEMODEMODEMO';

    public function run(
        CreateOperatorCommandHandler $createOperator,
        OperatorRepositoryInterface $operators,
        TransactionManagerInterface $transactionManager,
    ): void {
        $createOperator->handle(new CreateOperatorCommand(self::EMAIL, self::PASSWORD));

        $transactionManager->transactional(function () use ($operators): void {
            $operator = $operators->getLockedByEmail(new OperatorEmail(self::EMAIL));
            $operator->enrolTotp(new TotpSecret(self::TOTP_SECRET));
            $operators->save($operator);
        });
    }
}
