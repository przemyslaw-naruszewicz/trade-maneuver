<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\InvestmentConfiguration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidAnalyticsApplicationIdException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidBrokerageAccountException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidInvestmentConfigurationIdException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\BrokerageAccount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;

final class IdentifierValueObjectsTest extends TestCase
{
    public function test_values_are_kept_as_given(): void
    {
        $this->assertSame('DEMO-ACCOUNT-1', (new BrokerageAccount('DEMO-ACCOUNT-1'))->identifier());
        $this->assertSame('demo-analytics', (new AnalyticsApplicationId('demo-analytics'))->toString());
        $this->assertSame(
            '019a5a6e-0000-7000-8000-000000000001',
            (new InvestmentConfigurationId('019a5a6e-0000-7000-8000-000000000001'))->toString(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emptyValues(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => ['  '];
    }

    #[DataProvider('emptyValues')]
    public function test_an_empty_brokerage_account_is_rejected(string $identifier): void
    {
        $this->expectException(InvalidBrokerageAccountException::class);
        $this->expectExceptionMessage('The brokerage account must not be empty.');

        new BrokerageAccount($identifier);
    }

    #[DataProvider('emptyValues')]
    public function test_an_empty_analytics_application_identifier_is_rejected(string $identifier): void
    {
        $this->expectException(InvalidAnalyticsApplicationIdException::class);
        $this->expectExceptionMessage('The analytics application identifier must not be empty.');

        new AnalyticsApplicationId($identifier);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function identitiesThatAreNotUuids(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['demo-investment'];
        yield 'uppercase' => ['019A5A6E-0000-7000-8000-000000000001'];
        yield 'trailing newline' => ["019a5a6e-0000-7000-8000-000000000001\n"];
    }

    #[DataProvider('identitiesThatAreNotUuids')]
    public function test_an_identity_that_is_not_a_uuid_is_rejected(string $identity): void
    {
        $this->expectException(InvalidInvestmentConfigurationIdException::class);

        new InvestmentConfigurationId($identity);
    }
}
