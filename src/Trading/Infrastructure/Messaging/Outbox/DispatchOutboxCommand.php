<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;

/**
 * The long-running outbox dispatcher process. It polls for due entries, pausing while there are none, and stops after
 * the current batch on SIGTERM or SIGINT. A failure to reach RabbitMQ ends the process, and the process supervisor
 * restarts it; entries that were not confirmed stay due.
 */
#[Signature('trading:dispatch-outbox')]
#[Description('Publish committed outbox entries until stopped')]
final class DispatchOutboxCommand extends Command implements SignalableCommandInterface
{
    private const int IDLE_PAUSE_MICROSECONDS = 500_000;

    private bool $stopRequested = false;

    public function handle(OutboxDispatcher $dispatcher): int
    {
        while (! $this->stopRequested) {
            if ($dispatcher->dispatchDue() === 0) {
                usleep(self::IDLE_PAUSE_MICROSECONDS);
            }
        }

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
