<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Operator\Command\EnrolOperatorTotp;

use TradeManeuver\Trading\Application\Port\TotpAuthenticatorInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\Operator\Repository\OperatorRepositoryInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;

final readonly class EnrolOperatorTotpCommandHandler
{
    public function __construct(
        private OperatorRepositoryInterface $operators,
        private TotpAuthenticatorInterface $totpAuthenticator,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function handle(EnrolOperatorTotpCommand $command): TotpEnrolmentDto
    {
        $email = new OperatorEmail($command->email);
        $secret = $this->totpAuthenticator->generateSecret();

        $this->transactionManager->transactional(function () use ($email, $secret): void {
            $operator = $this->operators->getLockedByEmail($email);
            $operator->enrolTotp($secret);
            $this->operators->save($operator);
        });

        return new TotpEnrolmentDto($secret->toString(), $this->totpAuthenticator->provisioningUri($email, $secret));
    }
}
