<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trading:consume-trade-signals')]
#[Description('Consume trade signals from RabbitMQ until stopped')]
final class ConsumeTradeSignalsCommand extends Command
{
    public function handle(TradeSignalConsumer $consumer): int
    {
        $consumer->consume(static fn (): bool => false);

        return self::SUCCESS;
    }
}
