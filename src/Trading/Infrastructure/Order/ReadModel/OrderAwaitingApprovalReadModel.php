<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Order\ReadModel;

use Illuminate\Database\Eloquent\Model;

final class OrderAwaitingApprovalReadModel extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'trading_orders';

    protected $keyType = 'string';
}
