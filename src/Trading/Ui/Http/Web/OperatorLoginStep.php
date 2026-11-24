<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Web;

/**
 * A step of the operator login, each with its own limit of failed attempts.
 */
enum OperatorLoginStep: string
{
    case Password = 'password';
    case Totp = 'totp';
}
