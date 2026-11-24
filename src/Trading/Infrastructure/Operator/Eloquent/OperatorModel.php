<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Operator\Eloquent;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The operator account as the session guard reads it for the password step, and as the repository stores it.
 *
 * @property string $id
 * @property string $email
 * @property string $password
 * @property string|null $totp_secret
 */
final class OperatorModel extends Authenticatable
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'trading_operators';

    protected $keyType = 'string';

    /** @var list<string> */
    protected $hidden = ['password', 'totp_secret'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'totp_secret' => 'encrypted',
        ];
    }
}
