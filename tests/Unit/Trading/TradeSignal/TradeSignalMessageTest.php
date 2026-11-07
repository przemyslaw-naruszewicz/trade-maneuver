<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\TradeSignal;

use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\TradeSignalMessage;

final class TradeSignalMessageTest extends TestCase
{
    public function test_the_envelope_is_read_from_a_json_object(): void
    {
        $body = '{"id":"019A5A6E-0000-7000-8000-0000000000A1","source":"demo-analytics","type":"tm.trade-signal.v1","confidence":"0.85"}';

        $message = TradeSignalMessage::fromBody($body);

        $this->assertSame('019a5a6e-0000-7000-8000-0000000000a1', $message->id()?->toString());
        $this->assertSame('demo-analytics', $message->source()?->toString());
        $this->assertSame(hash('sha256', $body), $message->fingerprint());
        $this->assertSame([
            'id' => '019A5A6E-0000-7000-8000-0000000000A1',
            'source' => 'demo-analytics',
            'type' => 'tm.trade-signal.v1',
            'fingerprint' => hash('sha256', $body),
        ], $message->metadata());
        $this->assertSame('0.85', $message->fields()['confidence'] ?? null);
    }

    public function test_a_body_that_is_not_a_json_object_has_no_fields(): void
    {
        foreach (['', 'not json', '["demo"]', '"demo"', '42'] as $body) {
            $message = TradeSignalMessage::fromBody($body);

            $this->assertNull($message->fields(), $body);
            $this->assertNull($message->id(), $body);
            $this->assertSame(['id' => '', 'source' => '', 'type' => '', 'fingerprint' => hash('sha256', $body)], $message->metadata());
        }
    }

    public function test_an_empty_json_object_has_empty_fields(): void
    {
        $this->assertSame([], TradeSignalMessage::fromBody('{}')->fields());
    }

    public function test_an_invalid_id_or_source_is_absent(): void
    {
        $message = TradeSignalMessage::fromBody('{"id":"purchase","source":" ","type":7}');

        $this->assertNull($message->id());
        $this->assertNull($message->source());
        $this->assertSame('', $message->metadata()['type']);
    }

    public function test_large_integers_stay_strings_instead_of_floats(): void
    {
        $message = TradeSignalMessage::fromBody('{"horizon":{"sessions":123456789012345678901234567890}}');

        $this->assertSame(['sessions' => '123456789012345678901234567890'], $message->fields()['horizon'] ?? null);
    }

    public function test_a_body_nested_too_deeply_has_no_fields(): void
    {
        $this->assertNull(TradeSignalMessage::fromBody(str_repeat('{"a":', 64).'1'.str_repeat('}', 64))->fields());
        $this->assertNotNull(TradeSignalMessage::fromBody(str_repeat('{"a":', 63).'1'.str_repeat('}', 63))->fields());
    }
}
