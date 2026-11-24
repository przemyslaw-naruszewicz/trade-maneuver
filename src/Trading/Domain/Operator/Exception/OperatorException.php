<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\Exception;

use DomainException;

/**
 * A business rejection of an operator account operation; its message is safe to show to the caller.
 */
abstract class OperatorException extends DomainException {}
