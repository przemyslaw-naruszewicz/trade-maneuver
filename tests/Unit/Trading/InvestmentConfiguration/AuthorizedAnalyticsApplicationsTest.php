<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\InvestmentConfiguration;

use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AuthorizedAnalyticsApplications;

final class AuthorizedAnalyticsApplicationsTest extends TestCase
{
    public function test_the_set_may_be_empty(): void
    {
        $this->assertSame([], AuthorizedAnalyticsApplications::of()->identifiers());
    }

    public function test_the_set_holds_each_analytics_application_once_in_a_stable_order(): void
    {
        $authorized = AuthorizedAnalyticsApplications::of(
            new AnalyticsApplicationId('demo-research'),
            new AnalyticsApplicationId('demo-analytics'),
            new AnalyticsApplicationId('demo-research'),
        );

        $this->assertSame(['demo-analytics', 'demo-research'], $authorized->identifiers());
        $this->assertSame([0, 1], array_keys($authorized->all()));
    }

    public function test_the_set_includes_only_its_analytics_applications(): void
    {
        $authorized = AuthorizedAnalyticsApplications::of(new AnalyticsApplicationId('demo-analytics'));

        $this->assertTrue($authorized->includes(new AnalyticsApplicationId('demo-analytics')));
        $this->assertFalse($authorized->includes(new AnalyticsApplicationId('demo-research')));
        $this->assertFalse(AuthorizedAnalyticsApplications::of()->includes(new AnalyticsApplicationId('demo-analytics')));
    }
}
