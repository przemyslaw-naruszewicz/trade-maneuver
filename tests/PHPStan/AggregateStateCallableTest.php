<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Spaze\PHPStan\Rules\Disallowed\Calls\MethodFirstClassCallables;

/**
 * @extends RuleTestCase<MethodFirstClassCallables>
 */
final class AggregateStateCallableTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(MethodFirstClassCallables::class);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return AggregateStateFixtures::configFiles();
    }

    protected function setUp(): void
    {
        parent::setUp();

        AggregateStateFixtures::load();
    }

    public function test_only_first_class_callables_outside_a_repository_implementation_or_a_test_are_reported(): void
    {
        $message = AggregateStateFixtures::MESSAGE;

        $this->analyse(AggregateStateFixtures::files(), [
            [$message, 16],
        ]);
    }
}
