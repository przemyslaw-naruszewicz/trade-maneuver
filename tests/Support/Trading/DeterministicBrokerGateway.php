<?php

declare(strict_types=1);

namespace Tests\Support\Trading;

use Brick\Money\Currency;
use TradeManeuver\Trading\Application\Port\BrokerGatewayInterface;
use TradeManeuver\Trading\Application\Port\Exception\BrokerUnavailableException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\InstrumentListing;

/**
 * The broker gateway that tests script: it knows only the instruments a test lists and fails the lookups a test asks
 * it to fail.
 */
final class DeterministicBrokerGateway implements BrokerGatewayInterface
{
    /** @var array<string, InstrumentListing> listings by symbol and MIC */
    private array $listings = [];

    private int $lookupsToTimeOut = 0;

    public function listAvailable(string $symbol, string $mic, string $quoteCurrency): void
    {
        $this->listings[self::keyOf($symbol, $mic)] = InstrumentListing::available(Currency::of($quoteCurrency));
    }

    public function listUnavailable(string $symbol, string $mic, string $quoteCurrency): void
    {
        $this->listings[self::keyOf($symbol, $mic)] = InstrumentListing::unavailable(Currency::of($quoteCurrency));
    }

    public function timeOutNextLookups(int $count): void
    {
        $this->lookupsToTimeOut = $count;
    }

    public function lookUpInstrument(Instrument $instrument): ?InstrumentListing
    {
        if ($this->lookupsToTimeOut > 0) {
            $this->lookupsToTimeOut--;

            throw new BrokerUnavailableException('The broker did not answer the instrument lookup in time.');
        }

        return $this->listings[self::keyOf($instrument->symbol(), $instrument->mic()->code())] ?? null;
    }

    private static function keyOf(string $symbol, string $mic): string
    {
        return $symbol.'@'.$mic;
    }
}
