<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Operator;

use PragmaRX\Google2FA\Google2FA;
use TradeManeuver\Trading\Application\Port\TotpAuthenticatorInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpSecret;

/**
 * TOTP through pragmarx/google2fa, whose defaults are 6 digits, a 30-second step, and SHA-1.
 */
final readonly class Google2FaTotpAuthenticator implements TotpAuthenticatorInterface
{
    private const string ISSUER = 'Trade Maneuver';

    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): TotpSecret
    {
        return new TotpSecret($this->google2fa->generateSecretKey());
    }

    public function provisioningUri(OperatorEmail $email, TotpSecret $secret): string
    {
        return $this->google2fa->getQRCodeUrl(self::ISSUER, $email->toString(), $secret->toString());
    }
}
