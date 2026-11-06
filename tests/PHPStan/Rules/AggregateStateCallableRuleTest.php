<?php

declare(strict_types=1);

namespace Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<AggregateStateCallableRule>
 */
final class AggregateStateCallableRuleTest extends RuleTestCase
{
    private const string MESSAGE = 'Call to %s::state() outside a repository implementation or a test. The aggregate state is a persistence contract (ADR-0002).';

    private const string WIDGET = 'TradeManeuver\Fixture\Domain\Widget\Widget';

    protected function getRule(): Rule
    {
        return new AggregateStateCallableRule;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // PHPStan finds the fixture classes through the runtime reflection of loaded classes.
        foreach ($this->fixtures() as $fixture) {
            require_once $fixture;
        }
    }

    public function test_only_first_class_callables_outside_a_repository_implementation_or_a_test_are_reported(): void
    {
        $message = sprintf(self::MESSAGE, self::WIDGET);

        $this->analyse($this->fixtures(), [
            [$message, 16],
        ]);
    }

    /**
     * @return list<string>
     */
    private function fixtures(): array
    {
        return [
            __DIR__.'/data/Domain.php',
            __DIR__.'/data/Infrastructure.php',
            __DIR__.'/data/Application.php',
            __DIR__.'/data/Tests.php',
        ];
    }
}
