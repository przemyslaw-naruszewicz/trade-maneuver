<?php

declare(strict_types=1);

namespace Tests\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<AggregateStateCallRule>
 */
final class AggregateStateCallRuleTest extends RuleTestCase
{
    private const string MESSAGE = 'Call to %s::state() outside a repository implementation or a test. The aggregate state is a persistence contract (ADR-0002).';

    private const string WIDGET = 'TradeManeuver\Fixture\Domain\Widget\Widget';

    protected function getRule(): Rule
    {
        return new AggregateStateCallRule;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // PHPStan finds the fixture classes through the runtime reflection of loaded classes.
        foreach ($this->fixtures() as $fixture) {
            require_once $fixture;
        }
    }

    public function test_only_calls_outside_a_repository_implementation_or_a_test_are_reported(): void
    {
        $message = sprintf(self::MESSAGE, self::WIDGET);

        $this->analyse($this->fixtures(), [
            [$message, 16],
            [$message, 22],
            [$message, 14],
            [$message, 15],
            [$message, 16],
            [$message, 17],
            [$message, 24],
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
