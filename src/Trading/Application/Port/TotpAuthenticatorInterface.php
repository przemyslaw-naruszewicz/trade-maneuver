<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Port;

use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpSecret;

/**
 * Time-based one-time passwords with 6 digits and a 30-second step.
 */
interface TotpAuthenticatorInterface
{
    public function generateSecret(): TotpSecret;

    /**
     * The otpauth:// URI that an authenticator app reads to enrol the secret.
     */
    public function provisioningUri(OperatorEmail $email, TotpSecret $secret): string;
}
