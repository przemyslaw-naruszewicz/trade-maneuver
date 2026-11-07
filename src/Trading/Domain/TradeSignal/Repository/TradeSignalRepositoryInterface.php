<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\Repository;

use TradeManeuver\Trading\Domain\TradeSignal\Repository\Exception\TradeSignalDoesNotExistException;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

interface TradeSignalRepositoryInterface
{
    public function nextOutcomeIdentity(): SignalOutcomeId;

    public function add(TradeSignal $tradeSignal): void;

    /**
     * @throws TradeSignalDoesNotExistException
     */
    public function get(TradeSignalId $id): TradeSignal;
}
