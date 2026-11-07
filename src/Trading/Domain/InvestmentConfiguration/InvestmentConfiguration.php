<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\Dto\InvestmentConfigurationStateDto;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Event\InvestmentConfigurationCreated;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\OrderAmountCurrencyMismatchException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AuthorizedAnalyticsApplications;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\BrokerageAccount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;

final class InvestmentConfiguration
{
    /** @var list<InvestmentConfigurationCreated> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly InvestmentConfigurationId $id,
        private readonly InvestmentConfigurationRef $reference,
        private readonly InvestmentConfigurationCurrency $currency,
        private readonly BrokerageAccount $brokerageAccount,
        private readonly OrderAmount $orderAmount,
        private readonly LimitPriceTolerance $limitPriceTolerance,
        private readonly AuthorizedAnalyticsApplications $authorizedAnalyticsApplications,
    ) {
        $this->assertOrderAmountInCurrency($orderAmount);
    }

    public static function create(
        InvestmentConfigurationId $id,
        InvestmentConfigurationRef $reference,
        InvestmentConfigurationCurrency $currency,
        BrokerageAccount $brokerageAccount,
        OrderAmount $orderAmount,
        LimitPriceTolerance $limitPriceTolerance,
        AuthorizedAnalyticsApplications $authorizedAnalyticsApplications,
    ): self {
        $configuration = new self(
            $id,
            $reference,
            $currency,
            $brokerageAccount,
            $orderAmount,
            $limitPriceTolerance,
            $authorizedAnalyticsApplications,
        );
        $configuration->recordedEvents[] = new InvestmentConfigurationCreated(
            $id,
            $reference,
            $currency,
            $brokerageAccount,
            $orderAmount,
            $limitPriceTolerance,
            $authorizedAnalyticsApplications,
        );

        return $configuration;
    }

    /**
     * Rebuilds a stored investment configuration without recording domain events.
     */
    public static function reconstitute(InvestmentConfigurationId $id, InvestmentConfigurationStateDto $state): self
    {
        return new self(
            $id,
            $state->reference,
            $state->currency,
            $state->brokerageAccount,
            $state->orderAmount,
            $state->limitPriceTolerance,
            $state->authorizedAnalyticsApplications,
        );
    }

    public function id(): InvestmentConfigurationId
    {
        return $this->id;
    }

    /**
     * Whether the analytics application may address trade signals to this investment configuration.
     */
    public function authorizes(AnalyticsApplicationId $analyticsApplication): bool
    {
        return $this->authorizedAnalyticsApplications->includes($analyticsApplication);
    }

    public function state(): InvestmentConfigurationStateDto
    {
        return new InvestmentConfigurationStateDto(
            $this->reference,
            $this->currency,
            $this->brokerageAccount,
            $this->orderAmount,
            $this->limitPriceTolerance,
            $this->authorizedAnalyticsApplications,
        );
    }

    /**
     * @throws OrderAmountCurrencyMismatchException
     */
    private function assertOrderAmountInCurrency(OrderAmount $orderAmount): void
    {
        if (! $orderAmount->currency()->isEqualTo($this->currency->currency())) {
            throw new OrderAmountCurrencyMismatchException(sprintf(
                'The order amount must be in the investment configuration currency %s, not %s.',
                $this->currency->code(),
                $orderAmount->currency()->getCurrencyCode(),
            ));
        }
    }

    /**
     * Only creation records an event, so a mutant that keeps just the first event is equivalent.
     *
     * @infection-ignore-all
     *
     * @return list<InvestmentConfigurationCreated>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
