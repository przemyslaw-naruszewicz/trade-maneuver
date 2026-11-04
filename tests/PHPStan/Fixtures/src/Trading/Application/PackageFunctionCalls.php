<?php

declare(strict_types=1);

namespace Tests\PHPStan\Fixtures\src\Trading\Application;

final class PackageFunctionCalls
{
    public function helper(): mixed
    {
        return now();
    }
}
