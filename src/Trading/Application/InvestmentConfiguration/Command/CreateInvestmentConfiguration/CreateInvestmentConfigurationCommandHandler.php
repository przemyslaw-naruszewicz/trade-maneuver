<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration;

use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\ClockInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Event\InvestmentConfigurationCreated;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvestmentConfigurationAlreadyExistsException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvestmentConfigurationRefAlreadyUsedException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\InvestmentConfiguration;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AuthorizedAnalyticsApplications;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\BrokerageAccount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;

final readonly class CreateInvestmentConfigurationCommandHandler
{
    private const string ENTITY_TYPE = 'investment_configuration';

    public function __construct(
        private InvestmentConfigurationRepositoryInterface $investmentConfigurations,
        private AuditTrailInterface $auditTrail,
        private ClockInterface $clock,
    ) {}

    public function handle(CreateInvestmentConfigurationCommand $command): void
    {
        $reference = new InvestmentConfigurationRef($command->reference);
        $investmentConfiguration = InvestmentConfiguration::create(
            $this->investmentConfigurations->nextIdentity(),
            $reference,
            InvestmentConfigurationCurrency::fromCode($command->currency),
            new BrokerageAccount($command->brokerageAccount),
            OrderAmount::of($command->orderAmount, $command->orderAmountCurrency),
            LimitPriceTolerance::fromPercentage($command->limitPriceTolerance),
            $this->authorizedAnalyticsApplications($command->authorizedAnalyticsApplications),
        );

        if ($this->investmentConfigurations->existsWithReference($reference)) {
            throw InvestmentConfigurationRefAlreadyUsedException::forReference($reference);
        }

        // This iteration has exactly one investment configuration.
        if ($this->investmentConfigurations->exists()) {
            throw InvestmentConfigurationAlreadyExistsException::create();
        }

        $this->investmentConfigurations->add($investmentConfiguration);
        foreach ($investmentConfiguration->releaseEvents() as $event) {
            $this->auditTrail->record($this->auditRecordOf($event, $command->actor));
        }
    }

    /**
     * @param  list<string>  $identifiers
     */
    private function authorizedAnalyticsApplications(array $identifiers): AuthorizedAnalyticsApplications
    {
        $analyticsApplications = [];
        foreach ($identifiers as $identifier) {
            $analyticsApplications[] = new AnalyticsApplicationId($identifier);
        }

        return AuthorizedAnalyticsApplications::of(...$analyticsApplications);
    }

    private function auditRecordOf(InvestmentConfigurationCreated $event, string $actor): AuditRecordDto
    {
        return new AuditRecordDto(
            $actor,
            $this->clock->now(),
            self::ENTITY_TYPE,
            $event->id->toString(),
            'created',
            [
                'reference' => $event->reference->toString(),
                'currency' => $event->currency->code(),
                'brokerageAccount' => $event->brokerageAccount->identifier(),
                'orderAmount' => $event->orderAmount->amount(),
                'limitPriceTolerance' => $event->limitPriceTolerance->toString(),
                'authorizedAnalyticsApplications' => $event->authorizedAnalyticsApplications->identifiers(),
            ],
        );
    }
}
