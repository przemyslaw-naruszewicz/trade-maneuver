<?php

declare(strict_types=1);

namespace Tests\PHPStan\Fixtures\src\Trading\Infrastructure;

use Tests\PHPStan\Fixtures\src\Trading\Domain\PackageFunctionTrait;

final class ClassUsingDomainTrait
{
    use PackageFunctionTrait;
}
