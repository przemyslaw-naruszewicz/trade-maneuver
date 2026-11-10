<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Order\Support;

use DateTimeImmutable;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\BrokerageAccount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderSettings;
use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\SignalOutcomeId;

/**
 * Creates orders on the fictional instrument DEMO_A at the fictional exchange DEMO, through the trade signal they
 * belong to.
 */
final class OrderBuilder
{
    public const string ID = '019a5a6e-0000-7000-8000-0000000000c1';

    public const string CREATED_AT = '2026-11-09T10:00:00.500000Z';

    public static function settings(string $orderAmount = '1000.00', string $limitPriceTolerance = '2.00'): OrderSettings
    {
        return new OrderSettings(
            OrderAmount::of($orderAmount, 'EUR'),
            LimitPriceTolerance::fromPercentage($limitPriceTolerance),
            InvestmentConfigurationCurrency::fromCode('EUR'),
            new BrokerageAccount('DEMO-ACCOUNT-1'),
        );
    }

    /**
     * The order of a trade signal with the reference price 10.00 EUR, under the settings 1000.00 EUR and 2 %: the
     * limit price 10.20 EUR and the quantity 98.
     */
    public static function awaitingApproval(string $id = self::ID): Order
    {
        $order = TradeSignalBuilder::received(referencePrice: '10.00')->createOrder(
            new OrderId($id),
            new InvestmentConfigurationId(InvestmentConfigurationBuilder::ID),
            self::settings(),
            new SignalOutcomeId(TradeSignalBuilder::OUTCOME_ID),
            new DateTimeImmutable(self::CREATED_AT),
        );
        assert($order instanceof Order);

        return $order;
    }
}
