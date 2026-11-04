<?php

declare(strict_types=1);

namespace Tests\PHPStan\Fixtures\src\Trading\Domain;

use function Illuminate\Support\php_binary;

final class PackageFunctionCalls
{
    public function unqualifiedHelper(): mixed
    {
        return config('demo');
    }

    public function qualifiedHelper(): mixed
    {
        return \now();
    }

    public function qualifiedNamespacedFunction(): mixed
    {
        return \Symfony\Component\String\u('demo');
    }

    public function importedFunction(): string
    {
        return php_binary();
    }
}
