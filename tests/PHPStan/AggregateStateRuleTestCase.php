<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use Illuminate\Foundation\Bootstrap\HandleExceptions;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * Runs a rule of spaze/phpstan-disallowed-calls with the project's phpstan.neon.dist on fixtures for the ADR-0002 entry:
 * an aggregate's state() is read only by repository implementations and tests.
 *
 * @template TRule of Rule
 *
 * @extends RuleTestCase<TRule>
 */
abstract class AggregateStateRuleTestCase extends RuleTestCase
{
    protected const string MESSAGE = 'Calling TradeManeuver\Fixture\Domain\Widget\Widget::state() is forbidden, the aggregate state is a persistence contract (ADR-0002). [TradeManeuver\Fixture\Domain\Widget\Widget::state() matches TradeManeuver\*\Domain\*::state()]';

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__.'/../../phpstan.neon.dist'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // PHPStan finds the fixture classes through the runtime reflection of loaded classes.
        foreach ($this->fixtures() as $fixture) {
            require_once $fixture;
        }
    }

    protected function tearDown(): void
    {
        // Larastan boots the Laravel application when the configuration is loaded, which registers error handlers.
        HandleExceptions::flushState($this);

        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    protected function fixtures(): array
    {
        return [
            __DIR__.'/data/Domain.php',
            __DIR__.'/data/Infrastructure.php',
            __DIR__.'/data/Application.php',
            __DIR__.'/data/Tests.php',
        ];
    }
}
