<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\Repository;

use TradeManeuver\Trading\Domain\Operator\Exception\OperatorAlreadyExistsException;
use TradeManeuver\Trading\Domain\Operator\Operator;
use TradeManeuver\Trading\Domain\Operator\Repository\Exception\OperatorDoesNotExistException;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;

interface OperatorRepositoryInterface
{
    public function nextIdentity(): OperatorId;

    /**
     * Refuses a second operator account: there is exactly one.
     *
     * @throws OperatorAlreadyExistsException
     */
    public function add(Operator $operator): void;

    /**
     * Loads the operator account and locks it until the transaction ends, so that concurrent writers wait.
     *
     * @throws OperatorDoesNotExistException
     */
    public function getLockedByEmail(OperatorEmail $email): Operator;

    public function save(Operator $operator): void;
}
