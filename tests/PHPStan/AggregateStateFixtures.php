<?php

declare(strict_types=1);

namespace Tests\PHPStan;

/**
 * Fixtures for the ADR-0002 rule in phpstan.neon.dist: an aggregate's state() is read only by repository implementations and tests.
 */
final class AggregateStateFixtures
{
    public const string MESSAGE = 'Calling TradeManeuver\Fixture\Domain\Widget\Widget::state() is forbidden, the aggregate state is a persistence contract (ADR-0002).';

    /**
     * @return list<string>
     */
    public static function files(): array
    {
        return [
            __DIR__.'/data/Domain.php',
            __DIR__.'/data/Infrastructure.php',
            __DIR__.'/data/Application.php',
            __DIR__.'/data/Tests.php',
        ];
    }

    /**
     * PHPStan finds the fixture classes through the runtime reflection of loaded classes.
     */
    public static function load(): void
    {
        foreach (self::files() as $file) {
            require_once $file;
        }
    }

    /**
     * @return list<string>
     */
    public static function configFiles(): array
    {
        return [__DIR__.'/../../phpstan.neon.dist'];
    }
}
