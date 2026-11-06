<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\InvestmentConfiguration\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Amounts stay decimal strings from PostgreSQL `numeric` columns, never floats.
 *
 * @property string $id
 * @property string $reference
 * @property string $currency
 * @property string $brokerage_account
 * @property string $order_amount
 * @property string $limit_price_tolerance
 * @property list<string> $authorized_analytics_applications
 */
final class InvestmentConfigurationModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'trading_investment_configurations';

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authorized_analytics_applications' => 'array',
        ];
    }
}
