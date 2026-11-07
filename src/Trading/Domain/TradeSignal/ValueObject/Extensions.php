<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\TradeSignal\ValueObject;

/**
 * Producer metadata that Trade Maneuver stores without interpreting it.
 */
final readonly class Extensions
{
    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(private array $values) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function of(array $values): self
    {
        return new self($values);
    }

    public function toJson(): string
    {
        if ($this->values === []) {
            return '{}';
        }

        return json_encode($this->values, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }
}
