<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal;

use DomainException;
use TradeManeuver\Trading\Domain\TradeSignal\Exception\InvalidTradeSignalValueException;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ContractViolation;

/**
 * Reads the fields of a decoded message by path, such as `instrument.mic`, and collects every contract violation found.
 * A JSON null counts as a missing field.
 */
final class MessageFields
{
    /** @var list<ContractViolation> */
    private array $violations = [];

    /**
     * @param  array<mixed>  $message
     */
    public function __construct(private readonly array $message) {}

    /**
     * @template TValue
     *
     * @param  callable(string): TValue  $create
     * @return TValue|null
     */
    public function required(string $path, callable $create): mixed
    {
        $value = $this->string($path);

        return $value === null ? null : $this->attempt($path, static fn (): mixed => $create($value));
    }

    public function string(string $path): ?string
    {
        $value = $this->valueAt($path);
        if ($value === null) {
            $this->violate($path, 'The field is required.');

            return null;
        }
        if (! is_string($value)) {
            $this->violate($path, 'The field must be a string.');

            return null;
        }

        return $value;
    }

    public function optionalString(string $path): ?string
    {
        return $this->has($path) ? $this->string($path) : null;
    }

    public function optionalInteger(string $path): ?int
    {
        $value = $this->valueAt($path);
        if ($value !== null && ! is_int($value)) {
            $this->violate($path, 'The field must be an integer.');

            return null;
        }

        return $value;
    }

    /**
     * Whether the field holds an object; reports a violation when it is missing or holds anything else.
     */
    public function requiredObject(string $path): bool
    {
        if (! $this->has($path)) {
            $this->violate($path, 'The field is required.');

            return false;
        }

        return $this->optionalObject($path) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function optionalObject(string $path): ?array
    {
        $value = $this->valueAt($path);
        if ($value === null) {
            return null;
        }
        if (! self::isObject($value)) {
            $this->violate($path, 'The field must be an object.');

            return null;
        }

        return $value;
    }

    public function has(string $path): bool
    {
        return $this->valueAt($path) !== null;
    }

    /**
     * Runs a domain construction and turns its refusal into a violation of the field, or of the part it names.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $create
     * @return TValue|null
     */
    public function attempt(string $path, callable $create): mixed
    {
        try {
            return $create();
        } catch (InvalidTradeSignalValueException $exception) {
            $this->violate($exception->part === null ? $path : $path.'.'.$exception->part, $exception->getMessage());
        } catch (DomainException $exception) {
            $this->violate($path, $exception->getMessage());
        }

        return null;
    }

    public function violate(string $path, string $message): void
    {
        $this->violations[] = new ContractViolation($path, $message);
    }

    /**
     * @return list<ContractViolation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * @phpstan-assert-if-true array<string, mixed> $value
     */
    private static function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }

    private function valueAt(string $path): mixed
    {
        $value = $this->message;
        foreach (explode('.', $path) as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
