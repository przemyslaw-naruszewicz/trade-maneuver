<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitOperatorPasswordRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string', 'max:4096'],
        ];
    }

    /**
     * The submitted address in the form the operator account stores it. The web middleware already trims input.
     */
    public function email(): string
    {
        return strtolower($this->string('email')->toString());
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
