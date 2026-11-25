<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\Repository\Exception;

use TradeManeuver\Trading\Domain\Operator\Exception\OperatorException;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;

final class OperatorDoesNotExistException extends OperatorException
{
    public static function withId(OperatorId $id): self
    {
        return new self(sprintf('The operator account "%s" does not exist.', $id->toString()));
    }

    public static function withEmail(OperatorEmail $email): self
    {
        return new self(sprintf('No operator account has the e-mail address "%s".', $email->toString()));
    }
}
