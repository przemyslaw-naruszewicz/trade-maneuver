<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Application\TradeSignal\Command\PublishTradeSignal;

use DateInterval;
use Psr\Clock\ClockInterface;
use TradeManeuver\DemoAnalytics\Application\Port\TradeSignalPublisherInterface;
use TradeManeuver\DemoAnalytics\Application\TradeSignal\Exception\TradeSignalNotPublishedException;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\DemoTradeSignal;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\Confidence;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\DemoInstrument;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\ExpectedMove;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\Horizon;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\ReferencePrice;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\UtcTime;

/**
 * Issues a trade signal from the given values and publishes it. Every value is validated before anything is published.
 * There is no outbox and no retry: a failed publication is reported, and the command can be run again.
 */
final readonly class PublishTradeSignalCommandHandler
{
    private const string DEFAULT_SIGNAL_VALIDITY = 'P3D';

    public function __construct(
        private TradeSignalPublisherInterface $publisher,
        private TradeSignalMessageTranslator $translator,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws InvalidTradeSignalException
     * @throws TradeSignalNotPublishedException
     */
    public function handle(PublishTradeSignalCommand $command): void
    {
        $tradeSignal = $this->issue($command);
        $body = $this->translator->toMessageBody($tradeSignal);

        $this->publisher->publish($tradeSignal->id, $body);
        if ($command->publishTwice) {
            $this->publisher->publish($tradeSignal->id, $body);
        }
    }

    private function issue(PublishTradeSignalCommand $command): DemoTradeSignal
    {
        $issuedAt = UtcTime::fromDateTime($this->clock->now());
        $validUntil = $command->validUntil === null
            ? $issuedAt->plus(new DateInterval(self::DEFAULT_SIGNAL_VALIDITY))
            : UtcTime::fromString($command->validUntil);

        return new DemoTradeSignal(
            $command->id,
            $issuedAt,
            new InvestmentConfigurationRef($command->investmentConfigurationRef),
            new DemoInstrument($command->symbol),
            Direction::fromString($command->direction),
            new Confidence($command->confidence),
            new Horizon($command->horizonSessions),
            new ExpectedMove($command->expectedMoveMin, $command->expectedMoveMax),
            new ReferencePrice($command->referencePrice, $command->currency, $issuedAt),
            $validUntil,
        );
    }
}
