<?php

declare(strict_types=1);

namespace Tests\PHPStan\Fixtures\src\Trading\Domain;

final class PackageFunctionCallable
{
    public function helper(): callable
    {
        return config(...);
    }
}
