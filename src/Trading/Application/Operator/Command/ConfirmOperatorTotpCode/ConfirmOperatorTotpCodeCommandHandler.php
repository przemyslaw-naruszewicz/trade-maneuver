<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Operator\Command\ConfirmOperatorTotpCode;

use TradeManeuver\Trading\Application\Operator\Exception\InvalidTotpCodeException;
use TradeManeuver\Trading\Application\Port\TotpAuthenticatorInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\Operator\Repository\OperatorRepositoryInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;

/**
 * Confirms the second login step: the code must match the operator's TOTP secret.
 */
final readonly class ConfirmOperatorTotpCodeCommandHandler
{
    public function __construct(
        private OperatorRepositoryInterface $operators,
        private TotpAuthenticatorInterface $totpAuthenticator,
        private TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * @throws InvalidTotpCodeException
     */
    public function handle(ConfirmOperatorTotpCodeCommand $command): void
    {
        $operatorId = new OperatorId($command->operatorId);

        $this->transactionManager->transactional(function () use ($operatorId, $command): void {
            $operator = $this->operators->getLocked($operatorId);
            $secret = $operator->totpSecret();
            if ($secret === null || $this->totpAuthenticator->matchingTimestep($secret, $command->code) === null) {
                throw InvalidTotpCodeException::create();
            }
        });
    }
}
