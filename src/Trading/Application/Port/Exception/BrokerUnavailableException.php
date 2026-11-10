<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port\Exception;

use RuntimeException;

/**
 * The broker did not answer, for example after a timeout. It is a recoverable failure, not a business rejection.
 */
final class BrokerUnavailableException extends RuntimeException {}
