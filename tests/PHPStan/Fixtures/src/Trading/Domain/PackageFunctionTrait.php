<?php

declare(strict_types=1);

namespace Tests\PHPStan\Fixtures\src\Trading\Domain;

trait PackageFunctionTrait
{
    public function helper(): mixed
    {
        return now();
    }
}
