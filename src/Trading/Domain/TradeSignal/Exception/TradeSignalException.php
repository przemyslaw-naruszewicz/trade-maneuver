<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\Exception;

use DomainException;

/**
 * A refusal of a trade signal value or operation; its message is safe to report to the analytics application.
 */
abstract class TradeSignalException extends DomainException {}
