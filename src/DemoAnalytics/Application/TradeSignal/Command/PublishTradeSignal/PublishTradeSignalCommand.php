<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Application\TradeSignal\Command\PublishTradeSignal;

/**
 * The values of a trade signal as given, before validation. Without `$validUntil` the signal validity ends three days
 * after the trade signal is issued.
 */
final readonly class PublishTradeSignalCommand
{
    public function __construct(
        public string $id,
        public string $investmentConfigurationRef,
        public string $symbol,
        public string $direction,
        public string $confidence,
        public string $horizonSessions,
        public string $expectedMoveMin,
        public string $expectedMoveMax,
        public string $referencePrice,
        public string $currency,
        public ?string $validUntil,
        public bool $publishTwice,
    ) {}
}
