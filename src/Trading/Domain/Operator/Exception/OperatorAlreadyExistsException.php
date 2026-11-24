<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\Exception;

final class OperatorAlreadyExistsException extends OperatorException
{
    public static function create(): self
    {
        return new self('The operator account already exists.');
    }
}
