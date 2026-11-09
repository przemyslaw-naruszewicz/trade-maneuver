<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Application\SignalOutcome\Command\ReadSignalOutcomes;

use TradeManeuver\DemoAnalytics\Application\Port\SignalOutcomeQueueInterface;
use TradeManeuver\DemoAnalytics\Application\SignalOutcome\Dto\SignalOutcomeDto;
use TradeManeuver\DemoAnalytics\Application\SignalOutcome\Exception\UnreadableSignalOutcomeException;

/**
 * Takes the signal outcomes from the demo analytics application's outcome queue. Each one is acknowledged only after
 * `$print` has handled it; one that cannot be read stops reading and stays in the queue.
 */
final readonly class ReadSignalOutcomesCommandHandler
{
    public function __construct(private SignalOutcomeQueueInterface $queue, private SignalOutcomeMessageReader $reader) {}

    /**
     * @param  callable(SignalOutcomeDto): void  $print
     *
     * @throws UnreadableSignalOutcomeException
     */
    public function handle(callable $print): void
    {
        $this->queue->takeEach(function (string $body) use ($print): void {
            $print($this->reader->read($body));
        });
    }
}
