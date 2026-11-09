<?php

declare(strict_types=1);

namespace Tests\Unit\DemoAnalytics;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\DemoAnalytics\Application\SignalOutcome\Command\ReadSignalOutcomes\SignalOutcomeMessageReader;
use TradeManeuver\DemoAnalytics\Application\SignalOutcome\Exception\UnreadableSignalOutcomeException;

final class SignalOutcomeMessageReaderTest extends TestCase
{
    public function test_it_reads_the_printed_fields_of_an_outcome_message(): void
    {
        $outcome = (new SignalOutcomeMessageReader)->read(json_encode([
            'id' => '019a5a6e-0000-7000-8000-0000000000b1',
            'signalId' => '019a5a6e-0000-7000-8000-0000000000a1',
            'outcome' => 'OrderExpired',
            'executedQuantity' => 0,
        ], JSON_THROW_ON_ERROR));

        $this->assertSame('019a5a6e-0000-7000-8000-0000000000a1', $outcome->signalId);
        $this->assertSame('OrderExpired', $outcome->outcome);
        $this->assertNull($outcome->reason);
        $this->assertSame(0, $outcome->executedQuantity);
        $this->assertSame([], $outcome->violations);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadableMessages(): iterable
    {
        $valid = ['signalId' => '019a5a6e-0000-7000-8000-0000000000a1', 'outcome' => 'SignalRejected'];
        yield 'not JSON' => ['{', 'the message is not JSON.'];
        yield 'not a JSON object' => ['"SignalRejected"', 'the message is not a JSON object.'];
        yield 'no signal id' => [self::body(['outcome' => 'SignalRejected']), 'the signalId must be a string.'];
        yield 'outcome that is not a string' => [self::body([...$valid, 'outcome' => 1]), 'the outcome must be a string.'];
        yield 'reason that is not a string' => [self::body([...$valid, 'reason' => null]), 'the reason must be a string.'];
        yield 'executed quantity that is not an integer' => [self::body([...$valid, 'executedQuantity' => '3']), 'the executedQuantity must be an integer.'];
        yield 'violations that are not a list' => [self::body([...$valid, 'violations' => 'confidence']), 'the violations must be a list.'];
        $violation = 'each violation must have a field and a message.';
        yield 'violation that is not an object' => [self::body([...$valid, 'violations' => ['confidence']]), $violation];
        yield 'violation without a field' => [self::body([...$valid, 'violations' => [['message' => 'Invalid.']]]), $violation];
        yield 'violation without a message' => [self::body([...$valid, 'violations' => [['field' => 'confidence']]]), $violation];
    }

    #[DataProvider('unreadableMessages')]
    public function test_it_refuses_a_message_without_the_printed_fields(string $body, string $expectedMessage): void
    {
        $this->expectException(UnreadableSignalOutcomeException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new SignalOutcomeMessageReader)->read($body);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private static function body(array $fields): string
    {
        return json_encode($fields, JSON_THROW_ON_ERROR);
    }
}
