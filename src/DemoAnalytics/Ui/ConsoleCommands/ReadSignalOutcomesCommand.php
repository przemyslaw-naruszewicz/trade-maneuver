<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo-analytics:read-signal-outcomes')]
#[Description('Print and acknowledge the signal outcomes in the demo analytics application\'s outcome queue')]
final class ReadSignalOutcomesCommand extends Command
{
    public function handle(): int
    {
        return self::SUCCESS;
    }
}
