<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Operator;

use PragmaRX\Google2FA\Google2FA;
use TradeManeuver\Trading\Application\Port\TotpAuthenticatorInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpSecret;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpTimestep;

/**
 * TOTP through pragmarx/google2fa, whose defaults are 6 digits, a 30-second step, and SHA-1.
 */
final readonly class Google2FaTotpAuthenticator implements TotpAuthenticatorInterface
{
    private const string ISSUER = 'Trade Maneuver';

    private const int TOLERANCE_IN_STEPS = 1;

    private const string CODE_PATTERN = '/^\d{6}$/D';

    public function __construct(private Google2FA $google2fa) {}

    public function generateSecret(): TotpSecret
    {
        return new TotpSecret($this->google2fa->generateSecretKey());
    }

    public function provisioningUri(OperatorEmail $email, TotpSecret $secret): string
    {
        return $this->google2fa->getQRCodeUrl(self::ISSUER, $email->toString(), $secret->toString());
    }

    public function matchingTimestep(TotpSecret $secret, #[\SensitiveParameter] string $code): ?TotpTimestep
    {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            return null;
        }

        // With an earlier time step the library returns the matching step instead of true; 0 excludes no real step.
        $timestep = $this->google2fa->verifyKeyNewer($secret->toString(), $code, 0, self::TOLERANCE_IN_STEPS);

        return is_int($timestep) ? new TotpTimestep($timestep) : null;
    }
}
