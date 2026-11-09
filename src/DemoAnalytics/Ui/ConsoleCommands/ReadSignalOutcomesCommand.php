<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use TradeManeuver\DemoAnalytics\Application\SignalOutcome\Command\ReadSignalOutcomes\ReadSignalOutcomesCommandHandler;
use TradeManeuver\DemoAnalytics\Application\SignalOutcome\Dto\SignalOutcomeDto;
use TradeManeuver\DemoAnalytics\Application\SignalOutcome\Exception\UnreadableSignalOutcomeException;

#[Signature('demo-analytics:read-signal-outcomes')]
#[Description('Print and acknowledge the signal outcomes in the demo analytics application\'s outcome queue')]
final class ReadSignalOutcomesCommand extends Command
{
    public function handle(ReadSignalOutcomesCommandHandler $handler): int
    {
        try {
            $count = $handler->handle($this->print(...));
        } catch (UnreadableSignalOutcomeException $exception) {
            $this->error('A signal outcome could not be read: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($count === 0) {
            $this->line(sprintf('The outcome queue %s is empty.', (string) config('demo_analytics.outcome_queue')));
        }

        return self::SUCCESS;
    }

    private function print(SignalOutcomeDto $signalOutcome): void
    {
        $this->line(sprintf('Trade signal %s: %s', $signalOutcome->signalId, $signalOutcome->outcome));
        if ($signalOutcome->reason !== null) {
            $this->line('  reason: '.$signalOutcome->reason);
        }
        if ($signalOutcome->executedQuantity !== null) {
            $this->line('  executed quantity: '.$signalOutcome->executedQuantity);
        }
        foreach ($signalOutcome->violations as $violation) {
            $this->line(sprintf('  %s: %s', $violation['field'], $violation['message']));
        }
    }
}
