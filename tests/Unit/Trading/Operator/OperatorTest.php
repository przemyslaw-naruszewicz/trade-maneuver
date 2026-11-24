<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\Operator;

use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\Operator\Dto\OperatorStateDto;
use TradeManeuver\Trading\Domain\Operator\Exception\TotpCodeAlreadyUsedException;
use TradeManeuver\Trading\Domain\Operator\Operator;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;
use TradeManeuver\Trading\Domain\Operator\ValueObject\PasswordHash;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpSecret;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpTimestep;

final class OperatorTest extends TestCase
{
    private const string ID = '019a5a6e-0000-7000-8000-000000000010';

    public function test_a_new_operator_has_no_totp_enrolment(): void
    {
        $operator = $this->newOperator();

        $this->assertSame(self::ID, $operator->id()->toString());
        $this->assertSame('operator@example.test', $operator->email()->toString());
        $this->assertNull($operator->totpSecret());
        $state = $operator->state();
        $this->assertSame('a-password-hash', $state->passwordHash->toString());
        $this->assertNull($state->totpSecret);
        $this->assertNull($state->lastAcceptedTotpTimestep);
    }

    public function test_a_password_change_replaces_the_password_hash(): void
    {
        $operator = $this->newOperator();

        $operator->changePassword(new PasswordHash('a-new-password-hash'));

        $this->assertSame('a-new-password-hash', $operator->state()->passwordHash->toString());
    }

    public function test_the_first_code_after_enrolment_is_accepted_and_recorded(): void
    {
        $operator = $this->enrolledOperator();

        $operator->acceptTotpCodeAt(new TotpTimestep(1000));

        $this->assertSame(1000, $operator->state()->lastAcceptedTotpTimestep?->value());
    }

    public function test_a_code_from_a_later_time_step_is_accepted(): void
    {
        $operator = $this->enrolledOperator();
        $operator->acceptTotpCodeAt(new TotpTimestep(1000));

        $operator->acceptTotpCodeAt(new TotpTimestep(1001));

        $this->assertSame(1001, $operator->state()->lastAcceptedTotpTimestep?->value());
    }

    public function test_a_code_from_the_last_accepted_time_step_is_a_replay(): void
    {
        $operator = $this->enrolledOperator();
        $operator->acceptTotpCodeAt(new TotpTimestep(1000));

        $this->expectException(TotpCodeAlreadyUsedException::class);
        $this->expectExceptionMessage('A TOTP code was already accepted at time step 1000.');

        $operator->acceptTotpCodeAt(new TotpTimestep(1000));
    }

    public function test_a_code_from_an_earlier_time_step_is_a_replay_and_changes_nothing(): void
    {
        $operator = $this->enrolledOperator();
        $operator->acceptTotpCodeAt(new TotpTimestep(1000));

        try {
            $operator->acceptTotpCodeAt(new TotpTimestep(999));
            $this->fail('An earlier time step was accepted.');
        } catch (TotpCodeAlreadyUsedException) {
            $this->assertSame(1000, $operator->state()->lastAcceptedTotpTimestep?->value());
        }
    }

    public function test_a_new_enrolment_replaces_the_secret_and_forgets_the_last_accepted_time_step(): void
    {
        $operator = $this->enrolledOperator();
        $operator->acceptTotpCodeAt(new TotpTimestep(1000));

        $operator->enrolTotp(new TotpSecret('MFRGGZDFMZTWQ2LK'));

        $this->assertSame('MFRGGZDFMZTWQ2LK', $operator->totpSecret()?->toString());
        $this->assertNull($operator->state()->lastAcceptedTotpTimestep);
    }

    public function test_reconstitution_keeps_the_stored_state(): void
    {
        $state = new OperatorStateDto(
            new OperatorEmail('operator@example.test'),
            new PasswordHash('a-password-hash'),
            new TotpSecret('JBSWY3DPEHPK3PXP'),
            new TotpTimestep(1000),
        );

        $operator = Operator::reconstitute(new OperatorId(self::ID), $state);

        $this->assertEquals($state, $operator->state());
        $this->expectException(TotpCodeAlreadyUsedException::class);
        $operator->acceptTotpCodeAt(new TotpTimestep(1000));
    }

    private function newOperator(): Operator
    {
        return Operator::create(new OperatorId(self::ID), new OperatorEmail('operator@example.test'), new PasswordHash('a-password-hash'));
    }

    private function enrolledOperator(): Operator
    {
        $operator = $this->newOperator();
        $operator->enrolTotp(new TotpSecret('JBSWY3DPEHPK3PXP'));

        return $operator;
    }
}
