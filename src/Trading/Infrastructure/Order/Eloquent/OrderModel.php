<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Order\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Decimals stay strings from PostgreSQL `numeric` columns, never floats. Times are read as PostgreSQL text.
 *
 * @property string $id
 * @property string $trade_signal_id
 * @property string $investment_configuration_id
 * @property string $status
 * @property string $execution_progress
 * @property string $direction
 * @property string $instrument_symbol
 * @property string $instrument_mic
 * @property string $limit_price
 * @property int $quantity
 * @property string $order_amount
 * @property string $limit_price_tolerance
 * @property string $currency
 * @property string $brokerage_account
 * @property string $created_at
 */
final class OrderModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'trading_orders';

    protected $keyType = 'string';
}
