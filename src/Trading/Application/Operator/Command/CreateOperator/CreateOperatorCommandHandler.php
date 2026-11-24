<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Operator\Command\CreateOperator;

use TradeManeuver\Trading\Application\Port\PasswordHasherInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\Operator\Operator;
use TradeManeuver\Trading\Domain\Operator\Repository\OperatorRepositoryInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;

final readonly class CreateOperatorCommandHandler
{
    public function __construct(
        private OperatorRepositoryInterface $operators,
        private PasswordHasherInterface $passwordHasher,
        private TransactionManagerInterface $transactionManager,
    ) {}

    public function handle(CreateOperatorCommand $command): void
    {
        $operator = Operator::create(
            $this->operators->nextIdentity(),
            new OperatorEmail($command->email),
            $this->passwordHasher->hash($command->password),
        );

        $this->transactionManager->transactional(function () use ($operator): void {
            $this->operators->add($operator);
        });
    }
}
