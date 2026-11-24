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

        try {
            $model->save();
        } catch (UniqueConstraintViolationException) {
            // The single-row index refuses a second operator account, also under concurrent runs.
            throw OperatorAlreadyExistsException::create();
        }
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

        OperatorModel::query()->whereKey($operator->id()->toString())->update([
            'password' => $state->passwordHash->toString(),
        ]);
    }

    private function reconstitute(OperatorModel $model): Operator
    {
        return Operator::reconstitute(
            new OperatorId($model->id),
            new OperatorStateDto(new OperatorEmail($model->email), new PasswordHash($model->password)),
        );
    }
}
