<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Ui\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitOperatorTotpCodeRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:16'],
        ];
    }

    /**
     * The code without the spaces an authenticator app may show between its digit groups.
     */
    public function code(): string
    {
        return str_replace(' ', '', $this->string('code')->toString());
    }
}
