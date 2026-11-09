<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;

/**
 * The long-running consumer process. SIGTERM and SIGINT let the current delivery finish before it stops; RabbitMQ
 * returns the prefetched deliveries that were not acknowledged to the queue. The command subscribes to the signals
 * through Symfony Console, because Laravel's trap() is disabled when APP_ENV is testing.
 */
#[Signature('trading:consume-trade-signals')]
#[Description('Consume trade signals from RabbitMQ until stopped')]
final class ConsumeTradeSignalsCommand extends Command implements SignalableCommandInterface
{
    private bool $stopRequested = false;

    public function handle(TradeSignalConsumer $consumer): int
    {
        $consumer->consume(fn (): bool => $this->stopRequested);

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    public function getSubscribedSignals(): array
    {
        return [SIGTERM, SIGINT];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->stopRequested = true;

        return false;
    }
}
