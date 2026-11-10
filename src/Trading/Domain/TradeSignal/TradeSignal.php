<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal;

use DateTimeImmutable;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderSettings;
use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderQuantity;
use TradeManeuver\Trading\Domain\TradeSignal\Dto\TradeSignalStateDto;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\DeclineReason;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalDeclined;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalReceived;
use TradeManeuver\Trading\Domain\TradeSignal\Event\TradeSignalRejected;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalStateException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolation;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolations;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\InstrumentListing;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcome;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

final class TradeSignal
{
    /** @var list<TradeSignalReceived|TradeSignalRejected|TradeSignalDeclined> */
    private array $recordedEvents = [];

    /**
     * @throws InvalidTradeSignalStateException
     */
    private function __construct(
        private readonly TradeSignalId $id,
        private readonly AnalyticsApplicationId $source,
        private readonly DateTimeImmutable $receivedAt,
        private readonly ?TradeSignalContent $content,
        private ?SignalOutcome $outcome,
    ) {
        if ($content === null && $outcome === null) {
            throw new InvalidTradeSignalStateException('A trade signal without valid content must have a signal outcome.');
        }
    }

    /**
     * Stores a valid trade signal, which then awaits evaluation.
     */
    public static function receive(
        TradeSignalId $id,
        AnalyticsApplicationId $source,
        TradeSignalContent $content,
        DateTimeImmutable $receivedAt,
    ): self {
        $tradeSignal = new self($id, $source, $receivedAt, $content, null);
        $tradeSignal->recordedEvents[] = new TradeSignalReceived($id, $source, $content, $receivedAt);

        return $tradeSignal;
    }

    /**
     * Ends a trade signal whose message violates the contract with the signal outcome `SignalRejected`.
     */
    public static function reject(
        TradeSignalId $id,
        AnalyticsApplicationId $source,
        ContractViolations $violations,
        SignalOutcomeId $outcomeId,
        DateTimeImmutable $rejectedAt,
    ): self {
        $outcome = SignalOutcome::rejection($outcomeId, $violations, $rejectedAt);
        $tradeSignal = new self($id, $source, $rejectedAt, null, $outcome);
        $tradeSignal->recordedEvents[] = new TradeSignalRejected($id, $source, $outcome, $violations);

        return $tradeSignal;
    }

    /**
     * Rebuilds a stored trade signal without recording domain events.
     *
     * @throws InvalidTradeSignalStateException
     */
    public static function reconstitute(TradeSignalId $id, TradeSignalStateDto $state): self
    {
        return new self($id, $state->source, $state->receivedAt, $state->content, $state->outcome);
    }

    public function id(): TradeSignalId
    {
        return $this->id;
    }

    public function state(): TradeSignalStateDto
    {
        return new TradeSignalStateDto($this->source, $this->receivedAt, $this->content, $this->outcome);
    }

    /**
     * The evaluation checks that need no broker, in this order: a trade signal whose validity has ended by the time of
     * evaluation ends with `SignalDeclined` and the reason `SignalExpired`, and a short one with the reason
     * `UnsupportedDirection`. A trade signal that passes both awaits the instrument checks.
     *
     * @throws InvalidTradeSignalStateException when the trade signal already has a signal outcome
     */
    public function evaluateValidityAndDirection(SignalOutcomeId $outcomeId, DateTimeImmutable $evaluatedAt): void
    {
        $content = $this->contentAwaitingEvaluation();

        if ($evaluatedAt >= $content->validUntil->dateTime()) {
            $this->decline(DeclineReason::SignalExpired, $outcomeId, $evaluatedAt);
        } elseif ($content->direction === Direction::Short) {
            $this->decline(DeclineReason::UnsupportedDirection, $outcomeId, $evaluatedAt);
        }
    }

    /**
     * The checks of the instrument as the broker lists it, given as null when the broker does not know it. An unknown
     * or unavailable instrument ends the trade signal with `SignalDeclined` and the reason `InstrumentUnavailable`. A
     * reference price in a currency other than the instrument's quote currency then ends it with `SignalRejected`, a
     * contract violation that only the broker's listing reveals. Last, an instrument quoted in a currency other than
     * the investment configuration's ends it with `SignalDeclined` and the reason `UnsupportedCurrency`. A trade signal
     * that passes them awaits order creation.
     *
     * @throws InvalidTradeSignalStateException when the trade signal already has a signal outcome
     */
    public function checkInstrument(
        ?InstrumentListing $listing,
        InvestmentConfigurationCurrency $investmentConfigurationCurrency,
        SignalOutcomeId $outcomeId,
        DateTimeImmutable $checkedAt,
    ): void {
        $content = $this->contentAwaitingEvaluation();

        if ($listing === null || ! $listing->isAvailable()) {
            $this->decline(DeclineReason::InstrumentUnavailable, $outcomeId, $checkedAt);
        } elseif ($content->referencePrice->currencyCode() !== $listing->quoteCurrency()->getCurrencyCode()) {
            $this->rejectAtEvaluation(ContractViolations::of(new ContractViolation(
                'referencePrice.currency',
                sprintf('The reference price currency must be the instrument\'s quote currency %s.', $listing->quoteCurrency()->getCurrencyCode()),
            )), $outcomeId, $checkedAt);
        } elseif (! $listing->quoteCurrency()->isEqualTo($investmentConfigurationCurrency->currency())) {
            $this->decline(DeclineReason::UnsupportedCurrency, $outcomeId, $checkedAt);
        }
    }

    /**
     * Creates the order that a trade signal which passed evaluation leads to, priced and sized from the investment
     * configuration's order settings. When the order amount cannot buy one unit at the limit price, there is no order:
     * the trade signal ends with `SignalDeclined` and the reason `OrderAmountTooSmall`.
     *
     * @return Order|null the order awaiting approval, or null when the trade signal is declined
     *
     * @throws InvalidTradeSignalStateException when the trade signal already has a signal outcome
     */
    public function createOrder(
        OrderId $orderId,
        InvestmentConfigurationId $investmentConfigurationId,
        OrderSettings $settings,
        SignalOutcomeId $outcomeId,
        DateTimeImmutable $createdAt,
    ): ?Order {
        $content = $this->contentAwaitingEvaluation();
        $limitPrice = LimitPrice::proposedFor($content->referencePrice, $settings->limitPriceTolerance);
        $units = $limitPrice->unitsAffordableWith($settings->orderAmount);
        if ($units === 0) {
            $this->decline(DeclineReason::OrderAmountTooSmall, $outcomeId, $createdAt);

            return null;
        }

        return Order::createAwaitingApproval(
            $orderId,
            $this->id,
            $investmentConfigurationId,
            $content->instrument,
            $limitPrice,
            OrderQuantity::of($units),
            $settings,
            $createdAt,
        );
    }

    /**
     * The instrument that the broker checks.
     *
     * @throws InvalidTradeSignalStateException when the trade signal has no valid content
     */
    public function instrument(): Instrument
    {
        return $this->validContent()->instrument;
    }

    /**
     * The investment configuration that the trade signal addresses.
     *
     * @throws InvalidTradeSignalStateException when the trade signal has no valid content
     */
    public function investmentConfigurationRef(): InvestmentConfigurationRef
    {
        return $this->validContent()->investmentConfigurationRef;
    }

    public function awaitsEvaluation(): bool
    {
        return $this->outcome === null;
    }

    /**
     * The stored signal outcome, which a duplicate delivery receives again.
     */
    public function outcome(): ?SignalOutcome
    {
        return $this->outcome;
    }

    /**
     * @throws InvalidTradeSignalStateException
     */
    private function contentAwaitingEvaluation(): TradeSignalContent
    {
        if ($this->outcome !== null) {
            throw new InvalidTradeSignalStateException('Only a trade signal that awaits evaluation can be evaluated.');
        }

        return $this->validContent();
    }

    /**
     * @throws InvalidTradeSignalStateException
     */
    private function validContent(): TradeSignalContent
    {
        if ($this->content === null) {
            throw new InvalidTradeSignalStateException('A rejected trade signal without valid content has no instrument.');
        }

        return $this->content;
    }

    private function rejectAtEvaluation(ContractViolations $violations, SignalOutcomeId $outcomeId, DateTimeImmutable $rejectedAt): void
    {
        $this->outcome = SignalOutcome::rejection($outcomeId, $violations, $rejectedAt);
        $this->recordedEvents[] = new TradeSignalRejected($this->id, $this->source, $this->outcome, $violations);
    }

    private function decline(DeclineReason $reason, SignalOutcomeId $outcomeId, DateTimeImmutable $declinedAt): void
    {
        $this->outcome = SignalOutcome::decline($outcomeId, $reason, $declinedAt);
        $this->recordedEvents[] = new TradeSignalDeclined($this->id, $this->source, $this->outcome, $reason);
    }

    /**
     * Each transition records one event, so a mutant that keeps just the first event is equivalent.
     *
     * @infection-ignore-all
     *
     * @return list<TradeSignalReceived|TradeSignalRejected|TradeSignalDeclined>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
