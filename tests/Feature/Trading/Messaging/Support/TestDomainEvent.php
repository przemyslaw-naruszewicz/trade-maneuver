<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Messaging\Support;

/**
 * A domain event that only the tests of the domain event bus dispatch.
 */
final readonly class TestDomainEvent
{
    public function __construct(
        public string $name,
    ) {}
}
