<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Operator\Command\EnrolOperatorTotp;

/**
 * The new TOTP secret and its otpauth:// URI, shown to the operator once.
 */
final readonly class TotpEnrolmentDto
{
    public function __construct(
        #[\SensitiveParameter] public string $secret,
        #[\SensitiveParameter] public string $provisioningUri,
    ) {}
}
