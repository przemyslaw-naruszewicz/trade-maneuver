<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Application\SignalOutcome\Exception;

use RuntimeException;

/**
 * An outcome message that does not have the `tm.signal-outcome.v1` fields the demo analytics application prints.
 */
final class UnreadableSignalOutcomeException extends RuntimeException {}
