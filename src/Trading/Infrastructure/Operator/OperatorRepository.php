<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Operator;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use TradeManeuver\Trading\Domain\Operator\Dto\OperatorStateDto;
use TradeManeuver\Trading\Domain\Operator\Exception\OperatorAlreadyExistsException;
use TradeManeuver\Trading\Domain\Operator\Operator;
use TradeManeuver\Trading\Domain\Operator\Repository\Exception\OperatorDoesNotExistException;
use TradeManeuver\Trading\Domain\Operator\Repository\OperatorRepositoryInterface;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorEmail;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;
use TradeManeuver\Trading\Domain\Operator\ValueObject\PasswordHash;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpSecret;
use TradeManeuver\Trading\Domain\Operator\ValueObject\TotpTimestep;
use TradeManeuver\Trading\Infrastructure\Operator\Eloquent\OperatorModel;

final class OperatorRepository implements OperatorRepositoryInterface
{
    public function nextIdentity(): OperatorId
    {
        return new OperatorId(Str::uuid7()->toString());
    }

    public function add(Operator $operator): void
    {
        $state = $operator->state();

        $model = new OperatorModel;
        $model->id = $operator->id()->toString();
        $model->email = $state->email->toString();
        $model->password = $state->passwordHash->toString();
        $model->totp_secret = $state->totpSecret?->toString();

        try {
            $model->save();
        } catch (UniqueConstraintViolationException) {
            // The single-row index refuses a second operator account, also under concurrent runs.
            throw OperatorAlreadyExistsException::create();
        }
    }

    public function getLocked(OperatorId $id): Operator
    {
        $model = OperatorModel::query()->lockForUpdate()->find($id->toString());
        if ($model === null) {
            throw OperatorDoesNotExistException::withId($id);
        }

        return $this->reconstitute($model);
    }

    public function getLockedByEmail(OperatorEmail $email): Operator
    {
        $model = OperatorModel::query()->lockForUpdate()->where('email', $email->toString())->first();
        if ($model === null) {
            throw OperatorDoesNotExistException::withEmail($email);
        }

        return $this->reconstitute($model);
    }

    public function save(Operator $operator): void
    {
        $state = $operator->state();

        $model = OperatorModel::query()->findOrFail($operator->id()->toString());
        $model->password = $state->passwordHash->toString();
        $model->totp_secret = $state->totpSecret?->toString();
        $model->last_accepted_totp_timestep = $state->lastAcceptedTotpTimestep?->value();
        $model->save();
    }

    private function reconstitute(OperatorModel $model): Operator
    {
        return Operator::reconstitute(
            new OperatorId($model->id),
            new OperatorStateDto(
                new OperatorEmail($model->email),
                new PasswordHash($model->password),
                $model->totp_secret === null ? null : new TotpSecret($model->totp_secret),
                $model->last_accepted_totp_timestep === null ? null : new TotpTimestep($model->last_accepted_totp_timestep),
            ),
        );
    }
}
