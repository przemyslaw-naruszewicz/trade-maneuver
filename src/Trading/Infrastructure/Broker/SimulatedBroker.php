<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Broker;

use Brick\Money\Currency;
use InvalidArgumentException;
use TradeManeuver\Trading\Application\Port\BrokerGatewayInterface;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\InstrumentListing;

/**
 * The simulated broker, the only broker Trade Maneuver uses. It knows only the fictional instruments of its catalogue,
 * `trading.simulated_broker.instruments`, holds no market prices, and reads nothing but that catalogue, so that it can
 * later move to a separate service. An invalid catalogue entry fails when the simulated broker is created.
 */
final readonly class SimulatedBroker implements BrokerGatewayInterface
{
    /** @var array<string, InstrumentListing> listings by symbol and exchange */
    private array $listings;

    /**
     * @param  array<mixed>  $catalogue
     */
    public function __construct(array $catalogue)
    {
        $listings = [];
        foreach ($catalogue as $entry) {
            $entry = is_array($entry) ? $entry : [];
            $quoteCurrency = Currency::of(self::stringOf($entry, 'quote_currency'));
            $available = $entry['available'] ?? null;
            if (! is_bool($available)) {
                throw new InvalidArgumentException('Each entry in trading.simulated_broker.instruments needs a boolean "available".');
            }
            $key = self::keyOf(self::stringOf($entry, 'symbol'), self::stringOf($entry, 'exchange'));
            $listings[$key] = $available ? InstrumentListing::available($quoteCurrency) : InstrumentListing::unavailable($quoteCurrency);
        }
        $this->listings = $listings;
    }

    public function lookUpInstrument(Instrument $instrument): ?InstrumentListing
    {
        return $this->listings[self::keyOf($instrument->symbol(), $instrument->mic()->code())] ?? null;
    }

    /**
     * @param  array<mixed>  $entry
     */
    private static function stringOf(array $entry, string $key): string
    {
        $value = $entry[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Each entry in trading.simulated_broker.instruments needs a non-empty string "%s".', $key));
        }

        return $value;
    }

    private static function keyOf(string $symbol, string $exchange): string
    {
        return $symbol.'@'.$exchange;
    }
}
