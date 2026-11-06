<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\InvestmentConfiguration;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Dto\InvestmentConfigurationStateDto;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvestmentConfigurationAlreadyExistsException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\InvestmentConfiguration;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\Exception\InvestmentConfigurationDoesNotExistException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AuthorizedAnalyticsApplications;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\BrokerageAccount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;
use TradeManeuver\Trading\Infrastructure\InvestmentConfiguration\Eloquent\InvestmentConfigurationModel;

final class InvestmentConfigurationRepository implements InvestmentConfigurationRepositoryInterface
{
    public function nextIdentity(): InvestmentConfigurationId
    {
        return new InvestmentConfigurationId(Str::uuid7()->toString());
    }

    public function existsWithReference(InvestmentConfigurationRef $reference): bool
    {
        return InvestmentConfigurationModel::query()->where('reference', $reference->toString())->exists();
    }

    public function add(InvestmentConfiguration $investmentConfiguration): void
    {
        $state = $investmentConfiguration->state();

        $model = new InvestmentConfigurationModel;
        $model->id = $investmentConfiguration->id()->toString();
        $model->reference = $state->reference->toString();
        $model->currency = $state->currency->code();
        $model->brokerage_account = $state->brokerageAccount->identifier();
        $model->order_amount = $state->orderAmount->amount();
        $model->limit_price_tolerance = $state->limitPriceTolerance->toString();
        $model->authorized_analytics_applications = $state->authorizedAnalyticsApplications->identifiers();

        try {
            $model->save();
        } catch (UniqueConstraintViolationException) {
            // The single-row index refuses a second investment configuration, also under concurrent runs.
            throw InvestmentConfigurationAlreadyExistsException::create();
        }
    }

    public function get(InvestmentConfigurationId $id): InvestmentConfiguration
    {
        $model = InvestmentConfigurationModel::query()->find($id->toString());
        if ($model === null) {
            throw InvestmentConfigurationDoesNotExistException::withId($id);
        }

        return InvestmentConfiguration::reconstitute(
            new InvestmentConfigurationId($model->id),
            new InvestmentConfigurationStateDto(
                new InvestmentConfigurationRef($model->reference),
                InvestmentConfigurationCurrency::fromCode($model->currency),
                new BrokerageAccount($model->brokerage_account),
                OrderAmount::of($model->order_amount, $model->currency),
                LimitPriceTolerance::fromPercentage($model->limit_price_tolerance),
                $this->authorizedAnalyticsApplications($model->authorized_analytics_applications),
            ),
        );
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
}
