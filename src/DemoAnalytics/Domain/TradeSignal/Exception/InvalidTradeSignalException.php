<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception;

use DomainException;

/**
 * A value that the demo analytics application refuses to put into a trade signal.
 */
final class InvalidTradeSignalException extends DomainException {}
