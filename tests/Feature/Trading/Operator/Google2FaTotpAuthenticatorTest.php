<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpSecret;
use TradeManeuver\Trading\Infrastructure\Operator\Google2FaTotpAuthenticator;

final class Google2FaTotpAuthenticatorTest extends TestCase
{
    private const string SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    private Google2FA $google2fa;

    private Google2FaTotpAuthenticator $authenticator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->google2fa = new Google2FA;
        $this->authenticator = new Google2FaTotpAuthenticator($this->google2fa);
    }

    public function test_a_code_matches_its_time_step_within_one_step_of_now(): void
    {
        $now = $this->google2fa->getTimestamp();

        foreach ([$now - 1, $now, $now + 1] as $timestep) {
            $matched = $this->authenticator->matchingTimestep(new TotpSecret(self::SECRET), $this->codeAt($timestep));

            // A code can recur within the window, so the match is the earliest step with that code.
            $this->assertNotNull($matched);
            $this->assertSame($this->codeAt($timestep), $this->codeAt($matched->value()));
            $this->assertLessThanOrEqual($timestep, $matched->value());
        }
    }

    public function test_a_code_two_steps_away_from_now_does_not_match(): void
    {
        $now = $this->google2fa->getTimestamp();
        $windowCodes = [$this->codeAt($now - 1), $this->codeAt($now), $this->codeAt($now + 1)];

        foreach ([$now - 2, $now + 2] as $timestep) {
            $code = $this->codeAt($timestep);
            if (in_array($code, $windowCodes, true)) {
                continue;
            }

            $this->assertNull($this->authenticator->matchingTimestep(new TotpSecret(self::SECRET), $code));
        }
    }

    public function test_a_code_that_is_not_six_digits_does_not_match(): void
    {
        $code = $this->codeAt($this->google2fa->getTimestamp());

        foreach ([substr($code, 0, 5), $code.'0', ' '.$code, 'a'.substr($code, 1)] as $malformedCode) {
            $this->assertNull($this->authenticator->matchingTimestep(new TotpSecret(self::SECRET), $malformedCode));
        }
    }

    public function test_a_new_secret_has_32_base32_characters(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $this->authenticator->generateSecret()->toString());
        $this->assertNotSame($this->authenticator->generateSecret()->toString(), $this->authenticator->generateSecret()->toString());
    }

    public function test_the_provisioning_uri_names_the_issuer_and_the_account_with_6_digits_and_a_30_second_step(): void
    {
        $this->assertSame(
            'otpauth://totp/Trade%20Maneuver:operator%40example.test?secret='.self::SECRET
                .'&issuer=Trade%20Maneuver&algorithm=SHA1&digits=6&period=30',
            $this->authenticator->provisioningUri(new OperatorEmail('operator@example.test'), new TotpSecret(self::SECRET)),
        );
    }

    private function codeAt(int $timestep): string
    {
        return $this->google2fa->oathTotp(self::SECRET, $timestep);
    }
}
