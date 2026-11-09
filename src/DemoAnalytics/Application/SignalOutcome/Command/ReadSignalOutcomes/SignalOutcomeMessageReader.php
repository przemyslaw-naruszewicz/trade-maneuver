<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Application\SignalOutcome\Command\ReadSignalOutcomes;

use JsonException;
use TradeManeuver\DemoAnalytics\Application\SignalOutcome\Dto\SignalOutcomeDto;
use TradeManeuver\DemoAnalytics\Application\SignalOutcome\Exception\UnreadableSignalOutcomeException;

/**
 * Reads the fields of a `tm.signal-outcome.v1` message that the demo analytics application prints. As a Conformist it
 * takes the outcome and the decline reason as Trade Maneuver sends them, so a later outcome or reason code is printed too.
 */
final class SignalOutcomeMessageReader
{
    /**
     * @throws UnreadableSignalOutcomeException
     */
    public function read(string $body): SignalOutcomeDto
    {
        try {
            $message = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new UnreadableSignalOutcomeException('the message is not JSON.');
        }
        if (! is_array($message)) {
            throw new UnreadableSignalOutcomeException('the message is not a JSON object.');
        }

        return new SignalOutcomeDto(
            self::requiredString($message, 'signalId'),
            self::requiredString($message, 'outcome'),
            self::optionalReason($message),
            self::optionalExecutedQuantity($message),
            self::violations($message),
        );
    }

    /**
     * @param  array<mixed>  $message
     */
    private static function requiredString(array $message, string $field): string
    {
        $value = $message[$field] ?? null;
        if (! is_string($value)) {
            throw new UnreadableSignalOutcomeException(sprintf('the %s must be a string.', $field));
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $message
     */
    private static function optionalReason(array $message): ?string
    {
        return array_key_exists('reason', $message) ? self::requiredString($message, 'reason') : null;
    }

    /**
     * @param  array<mixed>  $message
     */
    private static function optionalExecutedQuantity(array $message): ?int
    {
        if (! array_key_exists('executedQuantity', $message)) {
            return null;
        }
        if (! is_int($message['executedQuantity'])) {
            throw new UnreadableSignalOutcomeException('the executedQuantity must be an integer.');
        }

        return $message['executedQuantity'];
    }

    /**
     * @param  array<mixed>  $message
     * @return list<array{field: string, message: string}>
     */
    private static function violations(array $message): array
    {
        $violations = [];
        foreach (self::violationEntries($message) as $entry) {
            if (! is_array($entry) || ! is_string($entry['field'] ?? null) || ! is_string($entry['message'] ?? null)) {
                throw new UnreadableSignalOutcomeException('each violation must have a field and a message.');
            }
            $violations[] = ['field' => $entry['field'], 'message' => $entry['message']];
        }

        return $violations;
    }

    /**
     * @param  array<mixed>  $message
     * @return array<mixed>
     */
    private static function violationEntries(array $message): array
    {
        $entries = $message['violations'] ?? [];
        if (! is_array($entries)) {
            throw new UnreadableSignalOutcomeException('the violations must be a list.');
        }

        return $entries;
    }
}
