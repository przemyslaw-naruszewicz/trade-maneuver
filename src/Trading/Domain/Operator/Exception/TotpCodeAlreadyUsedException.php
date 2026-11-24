<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator\Exception;

use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpTimestep;

final class TotpCodeAlreadyUsedException extends OperatorException
{
    public static function atOrBefore(TotpTimestep $lastAccepted): self
    {
        return new self(sprintf('A TOTP code was already accepted at time step %d.', $lastAccepted->value()));
    }
}
