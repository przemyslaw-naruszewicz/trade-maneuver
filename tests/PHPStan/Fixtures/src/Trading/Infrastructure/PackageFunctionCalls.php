<?php

declare(strict_types=1);

namespace Tests\PHPStan\Fixtures\src\Trading\Infrastructure;

final class PackageFunctionCalls
{
    public function helper(): mixed
    {
        return config('demo');
    }
}
