<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal;

final readonly class ReceiveTradeSignalCommandHandler
{
    public function handle(ReceiveTradeSignalCommand $command): TradeSignalIntakeResult
    {
        return TradeSignalIntakeResult::Accepted;
    }
}
