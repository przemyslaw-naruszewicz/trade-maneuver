<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal;

use DomainException;
use JsonException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;

/**
 * A delivered message body, decoded without trusting it. Decimal values stay strings: numbers are never read as floats.
 */
final readonly class TradeSignalMessage
{
    /**
     * @param  array<mixed>|null  $fields
     */
    private function __construct(private string $body, private ?array $fields) {}

    public static function fromBody(string $body): self
    {
        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            return new self($body, null);
        }

        $isObject = is_array($decoded) && ($decoded === [] || ! array_is_list($decoded));

        return new self($body, $isObject ? $decoded : null);
    }

    /**
     * The decoded JSON object, or null when the body is not one.
     *
     * @return array<mixed>|null
     */
    public function fields(): ?array
    {
        return $this->fields;
    }

    public function id(): ?TradeSignalId
    {
        $id = $this->stringField('id');
        try {
            return $id === null ? null : new TradeSignalId($id);
        } catch (DomainException) {
            return null;
        }
    }

    public function source(): ?AnalyticsApplicationId
    {
        $source = $this->stringField('source');
        try {
            return $source === null ? null : new AnalyticsApplicationId($source);
        } catch (DomainException) {
            return null;
        }
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->body);
    }

    /**
     * The envelope as delivered, for logs and the audit trail; a field that is not a string is reported as empty.
     *
     * @return array<string, string>
     */
    public function metadata(): array
    {
        return [
            'id' => $this->stringField('id') ?? '',
            'source' => $this->stringField('source') ?? '',
            'type' => $this->stringField('type') ?? '',
            'fingerprint' => $this->fingerprint(),
        ];
    }

    private function stringField(string $name): ?string
    {
        $value = $this->fields[$name] ?? null;

        return is_string($value) ? $value : null;
    }
}
