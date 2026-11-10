<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Order;

use DateTimeImmutable;
use Illuminate\Support\Str;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\BrokerageAccount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderSettings;
use TradeManeuver\Trading\Domain\Order\Dto\OrderStateDto;
use TradeManeuver\Trading\Domain\Order\Enum\ExecutionProgress;
use TradeManeuver\Trading\Domain\Order\Enum\OrderDirection;
use TradeManeuver\Trading\Domain\Order\Enum\OrderStatus;
use TradeManeuver\Trading\Domain\Order\Order;
use TradeManeuver\Trading\Domain\Order\Repository\Exception\OrderDoesNotExistException;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\ValueObject\LimitPrice;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderId;
use TradeManeuver\Trading\Domain\Order\ValueObject\OrderQuantity;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Mic;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;
use TradeManeuver\Trading\Infrastructure\Order\Eloquent\OrderModel;

final class OrderRepository implements OrderRepositoryInterface
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s.uP';

    public function nextIdentity(): OrderId
    {
        return new OrderId(Str::uuid7()->toString());
    }

    public function add(Order $order): void
    {
        $state = $order->state();

        $model = new OrderModel;
        $model->id = $order->id()->toString();
        $model->trade_signal_id = $state->tradeSignalId->toString();
        $model->investment_configuration_id = $state->investmentConfigurationId->toString();
        $model->status = $state->status->value;
        $model->execution_progress = $state->executionProgress->value;
        $model->direction = $state->direction->value;
        $model->instrument_symbol = $state->instrument->symbol();
        $model->instrument_mic = $state->instrument->mic()->code();
        $model->limit_price = $state->limitPrice->amount();
        $model->quantity = $state->quantity->units();
        $model->order_amount = $state->settings->orderAmount->amount();
        $model->limit_price_tolerance = $state->settings->limitPriceTolerance->toString();
        $model->currency = $state->settings->currency->code();
        $model->brokerage_account = $state->settings->brokerageAccount->identifier();
        $model->created_at = $state->createdAt->format(self::TIME_FORMAT);

        $model->save();
    }

    public function get(OrderId $id): Order
    {
        $model = OrderModel::query()->find($id->toString());
        if ($model === null) {
            throw OrderDoesNotExistException::withId($id);
        }

        return Order::reconstitute(
            new OrderId($model->id),
            new OrderStateDto(
                new TradeSignalId($model->trade_signal_id),
                new InvestmentConfigurationId($model->investment_configuration_id),
                OrderStatus::from($model->status),
                ExecutionProgress::from($model->execution_progress),
                OrderDirection::from($model->direction),
                new Instrument($model->instrument_symbol, new Mic($model->instrument_mic)),
                LimitPrice::of('0.01', $model->currency),
                OrderQuantity::of(1),
                new OrderSettings(
                    OrderAmount::of('0.01', $model->currency),
                    LimitPriceTolerance::fromPercentage('0'),
                    InvestmentConfigurationCurrency::fromCode($model->currency),
                    new BrokerageAccount($model->brokerage_account),
                ),
                new DateTimeImmutable($model->created_at),
            ),
        );
    }
}
