<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Order\ReadModel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An order awaiting approval with its trade signal, for display (ADR-0004). It reads the view
 * `trading_orders_awaiting_approval`, which joins orders and trade signals. PostgreSQL refuses writes through a view
 * that joins tables, so every write through this model, including through its query builder, throws a
 * `QueryException` and changes nothing. It loads no aggregates. Decimals stay strings from PostgreSQL `numeric`
 * columns, and times are PostgreSQL text.
 *
 * @property string $id
 * @property string $status
 * @property string $limit_price
 * @property string $currency
 * @property int $quantity
 * @property string $order_amount
 * @property string $execution_progress
 * @property string $trade_signal_id
 * @property string $instrument_symbol
 * @property string $instrument_mic
 * @property string $direction
 * @property string $confidence
 * @property int|null $horizon_sessions
 * @property string|null $horizon_at
 * @property string|null $expected_move_min
 * @property string|null $expected_move_max
 * @property string $reference_price_amount
 * @property string $reference_price_currency
 * @property string $reference_price_as_of
 * @property string $valid_until
 * @property string $analytics_application
 */
final class OrderAwaitingApprovalReadModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'trading_orders_awaiting_approval';

    protected $keyType = 'string';

    /**
     * The orders whose signal validity ends soonest come first.
     *
     * Eloquent forwards a static call to a method it cannot reach to a new instance, so a narrower visibility is an
     * equivalent mutant.
     *
     * @infection-ignore-all
     *
     * @return Builder<self>
     */
    public static function soonestValidUntilFirst(): Builder
    {
        return self::query()->orderBy('valid_until')->orderBy('id');
    }
}
