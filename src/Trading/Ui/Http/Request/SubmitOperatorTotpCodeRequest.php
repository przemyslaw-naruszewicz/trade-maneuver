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
            'code' => ['required', 'string', 'max:6'],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }
}
