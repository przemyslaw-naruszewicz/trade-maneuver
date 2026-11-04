<?php

declare(strict_types=1);

namespace Tests\PHPStan\Fixtures\src\Trading\Domain;

use Tests\PHPStan\Fixtures\src\Trading\Infrastructure\PackageFunctionTrait;

final class ClassUsingInfrastructureTrait
{
    use PackageFunctionTrait;
}
