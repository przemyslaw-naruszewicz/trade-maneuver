<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\TradeSignal\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Decimals stay strings from PostgreSQL `numeric` columns, never floats. Times are read as PostgreSQL text.
 *
 * @property string $id
 * @property string $source
 * @property string $received_at
 * @property string|null $issued_at
 * @property string|null $investment_configuration_ref
 * @property string|null $instrument_symbol
 * @property string|null $instrument_mic
 * @property string|null $direction
 * @property string|null $confidence
 * @property int|null $horizon_sessions
 * @property string|null $horizon_at
 * @property string|null $expected_move_min
 * @property string|null $expected_move_max
 * @property string|null $reference_price_amount
 * @property string|null $reference_price_currency
 * @property string|null $reference_price_as_of
 * @property string|null $valid_until
 * @property string|null $outcome_id
 * @property string|null $outcome
 * @property string|null $outcome_occurred_at
 * @property list<array{field: string, message: string}>|null $outcome_violations
 */
final class TradeSignalModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'trading_trade_signals';

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome_violations' => 'array',
        ];
    }
}
