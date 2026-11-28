<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Order\Support;

use RuntimeException;
use TradeManeuver\Trading\Application\Audit\AuditContext;
use TradeManeuver\Trading\Domain\Order\Event\OrderRefused;

/**
 * A domain event handler of the refusal that always fails, registered after the Trading handlers.
 */
final readonly class FailingOrderRefusedHandler
{
    public const string MESSAGE = 'A handler of the refusal failed for the test.';

    public function handle(OrderRefused $event, AuditContext $context): void
    {
        throw new RuntimeException(self::MESSAGE);
    }
}
