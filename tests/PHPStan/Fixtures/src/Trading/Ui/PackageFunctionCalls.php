<?php

declare(strict_types=1);

namespace Tests\PHPStan\Fixtures\src\Trading\Ui;

final class PackageFunctionCalls
{
    public function helper(): mixed
    {
        return config('demo');
    }
}
