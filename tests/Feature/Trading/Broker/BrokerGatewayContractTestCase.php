<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Broker;

use Tests\TestCase;
use TradeManeuver\Trading\Application\Port\BrokerGatewayInterface;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Mic;

/**
 * The port tests that every broker gateway passes: the simulated broker and the deterministic fake used in tests.
 */
abstract class BrokerGatewayContractTestCase extends TestCase
{
    /**
     * A broker gateway that lists DEMO_A as available and DEMO_H as unavailable, both quoted in EUR, and knows no
     * other instrument on the exchange DEMO.
     */
    abstract protected function gateway(): BrokerGatewayInterface;

    public function test_the_lookup_of_an_available_instrument_returns_its_availability_and_quote_currency(): void
    {
        $listing = $this->gateway()->lookUpInstrument(new Instrument('DEMO_A', new Mic('DEMO')));

        $this->assertNotNull($listing);
        $this->assertTrue($listing->isAvailable());
        $this->assertSame('EUR', $listing->quoteCurrency()->getCurrencyCode());
    }

    public function test_the_lookup_of_an_unavailable_instrument_returns_its_availability_and_quote_currency(): void
    {
        $listing = $this->gateway()->lookUpInstrument(new Instrument('DEMO_H', new Mic('DEMO')));

        $this->assertNotNull($listing);
        $this->assertFalse($listing->isAvailable());
        $this->assertSame('EUR', $listing->quoteCurrency()->getCurrencyCode());
    }

    public function test_the_lookup_of_an_unknown_instrument_returns_nothing(): void
    {
        $this->assertNull($this->gateway()->lookUpInstrument(new Instrument('DEMO_X', new Mic('DEMO'))));
    }

    public function test_an_instrument_is_identified_by_its_symbol_together_with_its_exchange(): void
    {
        $this->assertNull($this->gateway()->lookUpInstrument(new Instrument('DEMO_A', new Mic('DEM0'))));
    }
}
