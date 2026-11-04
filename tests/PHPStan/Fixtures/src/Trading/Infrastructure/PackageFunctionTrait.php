<?php

declare(strict_types=1);

namespace Tests\PHPStan\Fixtures\src\Trading\Infrastructure;

trait PackageFunctionTrait
{
    public function helper(): mixed
    {
        return now();
    }
}
