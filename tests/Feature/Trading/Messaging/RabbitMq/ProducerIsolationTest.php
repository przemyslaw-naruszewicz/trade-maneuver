<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Messaging\RabbitMq;

use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use Tests\Support\Trading\RabbitMqTestClient;
use Tests\TestCase;

/**
 * Producer permissions as docker/rabbitmq configures them, checked against the RabbitMQ of the tests.
 */
final class ProducerIsolationTest extends TestCase
{
    public function test_a_producer_user_cannot_read_another_producers_outcome_queue(): void
    {
        $producer = RabbitMqTestClient::asProducer('demo-analytics');

        try {
            $producer->channel()->basic_get(RabbitMqTestClient::outcomeQueueOf('demo-research'));
            $this->fail('Reading the outcome queue of demo-research as demo-analytics was expected to be refused.');
        } catch (AMQPProtocolChannelException $exception) {
            $this->assertStringContainsString('ACCESS_REFUSED', $exception->getMessage());
        } finally {
            $producer->close();
        }
    }
}
