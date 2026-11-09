<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Application\TradeSignal\Exception;

use RuntimeException;

/**
 * The message broker did not accept a trade signal, so the command can be run again.
 */
final class TradeSignalNotPublishedException extends RuntimeException {}
