<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Operator\Command\ConfirmOperatorTotpCode;

use TradeManeuver\Trading\Application\Operator\Exception\InvalidTotpCodeException;
use TradeManeuver\Trading\Application\Port\TotpAuthenticatorInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\Operator\Exception\TotpCodeAlreadyUsedException;
use TradeManeuver\Trading\Domain\Operator\Repository\OperatorRepositoryInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;

/**
 * Confirms the second login step: the code must match the operator's TOTP secret and must not have been used.
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
            // The lock makes a concurrent login with the same code wait and then see it as used.
            $operator = $this->operators->getLocked($operatorId);
            $secret = $operator->totpSecret();
            $timestep = $secret === null ? null : $this->totpAuthenticator->matchingTimestep($secret, $command->code);
            if ($timestep === null) {
                throw InvalidTotpCodeException::create();
            }

            try {
                $operator->acceptTotpCodeAt($timestep);
            } catch (TotpCodeAlreadyUsedException) {
                throw InvalidTotpCodeException::create();
            }
            $this->operators->save($operator);
        });
    }
}
