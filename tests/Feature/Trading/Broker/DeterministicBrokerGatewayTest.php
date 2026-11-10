<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Broker;

use Tests\Support\Trading\DeterministicBrokerGateway;
use TradeManeuver\Trading\Application\Port\BrokerGatewayInterface;
use TradeManeuver\Trading\Application\Port\Exception\BrokerUnavailableException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Mic;

final class DeterministicBrokerGatewayTest extends BrokerGatewayContractTestCase
{
    public function test_a_scripted_timeout_fails_only_the_next_lookups(): void
    {
        $gateway = new DeterministicBrokerGateway;
        $gateway->listAvailable('DEMO_A', 'DEMO', 'EUR');
        $gateway->timeOutNextLookups(1);
        $instrument = new Instrument('DEMO_A', new Mic('DEMO'));

        try {
            $gateway->lookUpInstrument($instrument);
            $this->fail('The scripted lookup was expected to time out.');
        } catch (BrokerUnavailableException $exception) {
            $this->assertSame('The broker did not answer the instrument lookup in time.', $exception->getMessage());
        }
        $this->assertNotNull($gateway->lookUpInstrument($instrument));
    }

    protected function gateway(): BrokerGatewayInterface
    {
        $gateway = new DeterministicBrokerGateway;
        $gateway->listAvailable('DEMO_A', 'DEMO', 'EUR');
        $gateway->listUnavailable('DEMO_H', 'DEMO', 'EUR');

        return $gateway;
    }
}
