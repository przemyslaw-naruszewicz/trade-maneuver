<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PHPStan\Rules\Rule;
use Spaze\PHPStan\Rules\Disallowed\Calls\MethodCalls;

/**
 * @extends AggregateStateRuleTestCase<MethodCalls>
 */
final class AggregateStateCallTest extends AggregateStateRuleTestCase
{
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(MethodCalls::class);
    }

    public function test_only_calls_outside_a_repository_implementation_or_a_test_are_reported(): void
    {
        $this->analyse($this->fixtures(), [
            [self::MESSAGE, 16],
            [self::MESSAGE, 22],
            [self::MESSAGE, 14],
            [self::MESSAGE, 15],
            [self::MESSAGE, 17],
            [self::MESSAGE, 24],
        ]);
    }
}
