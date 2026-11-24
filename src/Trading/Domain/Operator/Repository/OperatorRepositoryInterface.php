<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\Repository;

use TradeManeuver\Trading\Domain\Operator\Exception\OperatorAlreadyExistsException;
use TradeManeuver\Trading\Domain\Operator\Operator;
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
}
