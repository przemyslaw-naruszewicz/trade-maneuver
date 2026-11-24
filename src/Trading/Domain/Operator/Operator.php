<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\Operator;

use TradeManeuver\Trading\Domain\Operator\Dto\OperatorStateDto;
use TradeManeuver\Trading\Domain\Operator\Exception\TotpCodeAlreadyUsedException;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;
use TradeManeuver\Trading\Domain\Operator\ValueObject\PasswordHash;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpSecret;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpTimestep;

/**
 * The account of the single operator who supervises Trade Maneuver through the GUI.
 */
final class Operator
{
    private function __construct(
        private readonly OperatorId $id,
        private readonly OperatorEmail $email,
        private PasswordHash $passwordHash,
        private ?TotpSecret $totpSecret,
        private ?TotpTimestep $lastAcceptedTotpTimestep,
    ) {}

    public static function create(OperatorId $id, OperatorEmail $email, PasswordHash $passwordHash): self
    {
        return new self($id, $email, $passwordHash, null, null);
    }

    public static function reconstitute(OperatorId $id, OperatorStateDto $state): self
    {
        return new self(
            $id,
            $state->email,
            $state->passwordHash,
            $state->totpSecret,
            $state->lastAcceptedTotpTimestep,
        );
    }

    public function id(): OperatorId
    {
        return $this->id;
    }

    public function changePassword(PasswordHash $passwordHash): void
    {
        $this->passwordHash = $passwordHash;
    }

    /**
     * Enrols TOTP with a new secret, or replaces the secret of an earlier enrolment.
     */
    public function enrolTotp(TotpSecret $secret): void
    {
        $this->totpSecret = $secret;
        $this->lastAcceptedTotpTimestep = null;
    }

    /**
     * Accepts a code that matched the secret at the time step. Each code is accepted once: a code from the same or
     * an earlier time step than the last accepted one is a replay.
     *
     * @throws TotpCodeAlreadyUsedException
     */
    public function acceptTotpCodeAt(TotpTimestep $timestep): void
    {
        if ($this->lastAcceptedTotpTimestep !== null && ! $timestep->isAfter($this->lastAcceptedTotpTimestep)) {
            throw TotpCodeAlreadyUsedException::atOrBefore($this->lastAcceptedTotpTimestep);
        }

        $this->lastAcceptedTotpTimestep = $timestep;
    }

    /**
     * The secret to check a TOTP code against, or null before TOTP is enrolled.
     */
    public function totpSecret(): ?TotpSecret
    {
        return $this->totpSecret;
    }

    public function email(): OperatorEmail
    {
        return $this->email;
    }

    public function state(): OperatorStateDto
    {
        return new OperatorStateDto(
            $this->email,
            $this->passwordHash,
            $this->totpSecret,
            $this->lastAcceptedTotpTimestep,
        );
    }
}
