<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Order\ReadModel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An order in any status with its trade signal, for display (ADR-0004). It reads the view `trading_order_overview`,
 * which joins orders and trade signals. An order that reached a terminal status has an end time. PostgreSQL refuses writes through a view
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
 * @property string|null $ended_at
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
final class OrderOverviewReadModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'trading_order_overview';

    protected $keyType = 'string';

    /**
     * The orders awaiting approval, those whose signal validity ends soonest first.
     *
     * Eloquent forwards a static call to a method it cannot reach to a new instance, so a narrower visibility of a
     * static query is an equivalent mutant.
     *
     * @infection-ignore-all
     *
     * @return Builder<self>
     */
    public static function awaitingApproval(): Builder
    {
        return self::query()->where('status', 'awaiting_approval')->orderBy('valid_until')->orderBy('id');
    }

    /**
     * The orders that have not reached a terminal status and no longer await approval, those whose signal validity
     * ends soonest first.
     *
     * @infection-ignore-all
     *
     * @return Builder<self>
     */
    public static function otherNonTerminal(): Builder
    {
        return self::query()->whereNull('ended_at')->where('status', '!=', 'awaiting_approval')->orderBy('valid_until')->orderBy('id');
    }

    /**
     * The orders that reached a terminal status, the most recently ended first.
     *
     * @infection-ignore-all
     *
     * @return Builder<self>
     */
    public static function terminal(): Builder
    {
        return self::query()->whereNotNull('ended_at')->orderByDesc('ended_at')->orderByDesc('id');
    }
}
