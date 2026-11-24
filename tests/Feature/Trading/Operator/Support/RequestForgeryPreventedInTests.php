<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator\Support;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * The CSRF middleware as it runs outside tests: the framework skips the check while unit tests run.
 */
final class RequestForgeryPreventedInTests extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
