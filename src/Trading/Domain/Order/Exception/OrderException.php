<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Order\Exception;

use DomainException;

/**
 * A refusal of an order value or operation.
 */
abstract class OrderException extends DomainException {}
