<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Operator\Command\ResetOperatorPassword;

use TradeManeuver\Trading\Application\Port\PasswordHasherInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\Operator\Repository\OperatorRepositoryInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;

final readonly class ResetOperatorPasswordCommandHandler
{
    public function __construct(
        private OperatorRepositoryInterface $operators,
        private PasswordHasherInterface $passwordHasher,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function handle(ResetOperatorPasswordCommand $command): void
    {
        $email = new OperatorEmail($command->email);
        $passwordHash = $this->passwordHasher->hash($command->password);

        $this->transactionManager->transactional(function () use ($email, $passwordHash): void {
            $operator = $this->operators->getLockedByEmail($email);
            $operator->changePassword($passwordHash);
            $this->operators->save($operator);
        });
    }
}
