<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PHPStan\Rules\Rule;
use Spaze\PHPStan\Rules\Disallowed\Calls\MethodFirstClassCallables;

/**
 * @extends AggregateStateRuleTestCase<MethodFirstClassCallables>
 */
final class AggregateStateCallableTest extends AggregateStateRuleTestCase
{
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(MethodFirstClassCallables::class);
    }

    public function test_only_first_class_callables_outside_a_repository_implementation_or_a_test_are_reported(): void
    {
        $this->analyse($this->fixtures(), [
            [self::MESSAGE, 16],
        ]);
    }
}
