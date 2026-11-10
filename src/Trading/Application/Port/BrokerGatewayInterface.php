<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use TradeManeuver\Trading\Application\Port\Exception\BrokerUnavailableException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\InstrumentListing;

/**
 * The operations Trade Maneuver performs at the broker. Later tasks add the order operations.
 */
interface BrokerGatewayInterface
{
    /**
     * Looks up an instrument by its symbol and MIC; null when the broker does not know it.
     *
     * @throws BrokerUnavailableException when the broker does not answer, a recoverable failure
     */
    public function lookUpInstrument(Instrument $instrument): ?InstrumentListing;
}
