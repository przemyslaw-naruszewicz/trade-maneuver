<?php

declare(strict_types=1);

namespace Tests\PHPStan\Fixtures\src\Trading\Domain;

function project_function(): int
{
    return 1;
}

final class AllowedFunctionCalls
{
    public function phpFunction(): int
    {
        return strlen('demo');
    }

    public function projectFunction(): int
    {
        return project_function();
    }

    public function unknownFunction(): mixed
    {
        return unknown_function();
    }

    public function dynamicCall(callable $callable): mixed
    {
        return $callable();
    }
}
