<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Broker;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use TradeManeuver\Trading\Application\Port\BrokerGatewayInterface;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Mic;
use TradeManeuver\Trading\Infrastructure\Broker\SimulatedBroker;

/**
 * The simulated broker at its own boundary, with the catalogue it ships with.
 */
final class SimulatedBrokerTest extends BrokerGatewayContractTestCase
{
    public function test_the_shipped_catalogue_holds_only_demo_symbols_on_the_exchange_demo(): void
    {
        $catalogue = config('trading.simulated_broker.instruments');

        $this->assertIsArray($catalogue);
        $this->assertNotEmpty($catalogue);
        foreach ($catalogue as $entry) {
            $this->assertStringStartsWith('DEMO_', $entry['symbol']);
            $this->assertSame('DEMO', $entry['exchange']);
        }
    }

    public function test_the_shipped_catalogue_lists_demo_g_as_available_and_quoted_in_usd(): void
    {
        $listing = $this->gateway()->lookUpInstrument(new Instrument('DEMO_G', new Mic('DEMO')));

        $this->assertNotNull($listing);
        $this->assertTrue($listing->isAvailable());
        $this->assertSame('USD', $listing->quoteCurrency()->getCurrencyCode());
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidCatalogues(): iterable
    {
        $entry = ['symbol' => 'DEMO_A', 'exchange' => 'DEMO', 'quote_currency' => 'EUR', 'available' => true];

        yield 'not an entry' => [['DEMO_A'], 'Each entry in trading.simulated_broker.instruments needs a non-empty string "quote_currency".'];
        yield 'empty symbol' => [[[...$entry, 'symbol' => '']], 'Each entry in trading.simulated_broker.instruments needs a non-empty string "symbol".'];
        yield 'no exchange' => [[array_diff_key($entry, ['exchange' => true])], 'Each entry in trading.simulated_broker.instruments needs a non-empty string "exchange".'];
        yield 'availability as a string' => [[[...$entry, 'available' => 'yes']], 'Each entry in trading.simulated_broker.instruments needs a boolean "available".'];
    }

    /**
     * @param  array<mixed>  $catalogue
     */
    #[DataProvider('invalidCatalogues')]
    public function test_an_invalid_catalogue_entry_is_refused(array $catalogue, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new SimulatedBroker($catalogue);
    }

    protected function gateway(): BrokerGatewayInterface
    {
        return new SimulatedBroker((array) config('trading.simulated_broker.instruments'));
    }
}
