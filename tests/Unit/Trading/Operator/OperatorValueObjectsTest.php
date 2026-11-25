<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Operator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\Operator\Exception\InvalidOperatorValueException;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;
use TradeManeuver\Trading\Domain\Operator\ValueObject\PasswordHash;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpSecret;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpTimestep;

final class OperatorValueObjectsTest extends TestCase
{
    public function test_the_e_mail_address_is_kept_in_lower_case_without_surrounding_spaces(): void
    {
        $this->assertSame('operator@example.test', (new OperatorEmail(' Operator@Example.TEST '))->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEmailAddresses(): iterable
    {
        yield 'empty' => [''];
        yield 'without a domain' => ['operator@'];
        yield 'without an at sign' => ['operator.example.test'];
    }

    #[DataProvider('invalidEmailAddresses')]
    public function test_an_invalid_e_mail_address_is_rejected(string $email): void
    {
        $this->expectException(InvalidOperatorValueException::class);

        new OperatorEmail($email);
    }

    public function test_the_identity_must_be_a_uuid(): void
    {
        $this->assertSame('019a5a6e-0000-7000-8000-000000000010', (new OperatorId('019a5a6e-0000-7000-8000-000000000010'))->toString());

        $this->expectException(InvalidOperatorValueException::class);
        new OperatorId('019a5a6e-0000-7000-8000-000000000010 ');
    }

    public function test_an_empty_password_hash_is_rejected(): void
    {
        $this->assertSame('a-password-hash', (new PasswordHash('a-password-hash'))->toString());

        $this->expectException(InvalidOperatorValueException::class);
        new PasswordHash('');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTotpSecrets(): iterable
    {
        yield 'too short' => ['JBSWY3DPEHPK3PX'];
        yield 'lower case' => ['jbswy3dpehpk3pxp'];
        yield 'outside Base32' => ['JBSWY3DPEHPK3PX1'];
        yield 'with a trailing line break' => ["JBSWY3DPEHPK3PXP\n"];
    }

    #[DataProvider('invalidTotpSecrets')]
    public function test_a_totp_secret_outside_base32_or_shorter_than_16_characters_is_rejected(string $secret): void
    {
        $this->expectException(InvalidOperatorValueException::class);

        new TotpSecret($secret);
    }

    public function test_a_totp_secret_of_16_base32_characters_is_kept(): void
    {
        $this->assertSame('JBSWY3DPEHPK3PXP', (new TotpSecret('JBSWY3DPEHPK3PXP'))->toString());
    }

    public function test_time_steps_compare_by_value(): void
    {
        $this->assertTrue((new TotpTimestep(1001))->isAfter(new TotpTimestep(1000)));
        $this->assertFalse((new TotpTimestep(1000))->isAfter(new TotpTimestep(1000)));
        $this->assertFalse((new TotpTimestep(999))->isAfter(new TotpTimestep(1000)));
        $this->assertSame(0, (new TotpTimestep(0))->value());
    }

    public function test_a_negative_time_step_is_rejected(): void
    {
        $this->expectException(InvalidOperatorValueException::class);

        new TotpTimestep(-1);
    }
}
