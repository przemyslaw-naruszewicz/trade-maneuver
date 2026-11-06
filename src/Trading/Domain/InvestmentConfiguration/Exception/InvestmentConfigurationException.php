<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception;

use DomainException;

/**
 * A business rejection of an investment configuration operation; its message is safe to show to the caller.
 */
abstract class InvestmentConfigurationException extends DomainException {}
