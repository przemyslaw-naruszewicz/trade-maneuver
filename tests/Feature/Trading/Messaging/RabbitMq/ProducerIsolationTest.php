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

    public function test_rabbitmq_refuses_a_publication_whose_user_id_is_not_the_publishing_producer_user(): void
    {
        $producer = RabbitMqTestClient::asProducer('demo-analytics');

        try {
            $producer->publishTradeSignal('{}', 'demo-research');
            $this->fail('A publication by demo-analytics with the user_id demo-research was expected to be refused.');
        } catch (AMQPProtocolChannelException $exception) {
            $this->assertStringContainsString('PRECONDITION_FAILED', $exception->getMessage());
        } finally {
            $producer->close();
        }
    }
}
