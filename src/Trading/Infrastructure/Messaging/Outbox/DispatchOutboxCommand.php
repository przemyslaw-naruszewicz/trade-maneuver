<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trading:dispatch-outbox')]
#[Description('Publish committed outbox entries until stopped')]
final class DispatchOutboxCommand extends Command
{
    public function handle(): int
    {
        while (true) {
            sleep(1);
        }
    }
}
