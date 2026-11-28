<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Application\Audit;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\Operator\ValueObject\OperatorId;

/**
 * What a use case adds to the domain events it dispatches: the actor of their audit records, and audit values that
 * the use case holds but the events do not. Domain facts stay in the events.
 */
final readonly class AuditContext
{
    /**
     * @param  array<string, string>  $auditValues
     */
    public function __construct(
        public string $actor,
        public array $auditValues = [],
    ) {}

    /**
     * @param  array<string, string>  $auditValues
     */
    public static function ofAnalyticsApplication(AnalyticsApplicationId $analyticsApplication, array $auditValues): self
    {
        return new self('analytics-application:'.$analyticsApplication->toString(), $auditValues);
    }

    /**
     * @param  array<string, string>  $auditValues
     */
    public static function ofOperator(OperatorId $operator, array $auditValues = []): self
    {
        return new self('operator:'.$operator->toString(), $auditValues);
    }

    public static function ofTradeManeuver(): self
    {
        return new self('trade-maneuver');
    }
}
