<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\InvestmentConfiguration;

use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Tests\Unit\Trading\InvestmentConfiguration\Support\InvestmentConfigurationBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Dto\InvestmentConfigurationStateDto;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Event\InvestmentConfigurationCreated;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\OrderAmountCurrencyMismatchException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\InvestmentConfiguration;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AuthorizedAnalyticsApplications;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\BrokerageAccount;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationCurrency;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\LimitPriceTolerance;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\OrderAmount;

final class InvestmentConfigurationTest extends TestCase
{
    public function test_an_order_amount_in_another_currency_is_rejected(): void
    {
        $this->expectException(OrderAmountCurrencyMismatchException::class);
        $this->expectExceptionMessage('The order amount must be in the investment configuration currency EUR, not USD.');

        InvestmentConfigurationBuilder::anInvestmentConfiguration()
            ->withOrderAmount(OrderAmount::of('200.00', 'USD'))
            ->create();
    }

    public function test_creation_records_one_investment_configuration_created_event_with_the_created_values(): void
    {
        $investmentConfiguration = InvestmentConfigurationBuilder::anInvestmentConfiguration()->create();

        $events = $investmentConfiguration->releaseEvents();

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(InvestmentConfigurationCreated::class, $event);
        $this->assertSame(InvestmentConfigurationBuilder::ID, $event->id->toString());
        $this->assertSame('demo-investment', $event->reference->toString());
        $this->assertSame('EUR', $event->currency->code());
        $this->assertSame('DEMO-ACCOUNT-1', $event->brokerageAccount->identifier());
        $this->assertSame('200.00', $event->orderAmount->amount());
        $this->assertSame('1.50', $event->limitPriceTolerance->toString());
        $this->assertSame(['demo-analytics'], $event->authorizedAnalyticsApplications->identifiers());
        $this->assertSame([], $investmentConfiguration->releaseEvents());
    }

    public function test_reconstitution_keeps_the_stored_state_and_records_no_domain_events(): void
    {
        $created = InvestmentConfigurationBuilder::anInvestmentConfiguration()->create();

        $reconstituted = InvestmentConfiguration::reconstitute($created->id(), $created->state());

        $this->assertSame([], $reconstituted->releaseEvents());
        $this->assertSame(InvestmentConfigurationBuilder::ID, $reconstituted->id()->toString());
        $this->assertEquals($created->state(), $reconstituted->state());
    }

    public function test_reconstitution_rejects_stored_state_with_an_order_amount_in_another_currency(): void
    {
        $this->expectException(OrderAmountCurrencyMismatchException::class);

        InvestmentConfiguration::reconstitute(
            new InvestmentConfigurationId(InvestmentConfigurationBuilder::ID),
            new InvestmentConfigurationStateDto(
                new InvestmentConfigurationRef('demo-investment'),
                InvestmentConfigurationCurrency::fromCode('EUR'),
                new BrokerageAccount('DEMO-ACCOUNT-1'),
                OrderAmount::of('200.00', 'USD'),
                LimitPriceTolerance::fromPercentage('1.50'),
                AuthorizedAnalyticsApplications::of(),
            ),
        );
    }

    public function test_the_state_is_a_detached_copy(): void
    {
        $investmentConfiguration = InvestmentConfigurationBuilder::anInvestmentConfiguration()->create();

        $this->assertNotSame($investmentConfiguration->state(), $investmentConfiguration->state());
    }

    public function test_a_property_of_the_state_cannot_be_changed(): void
    {
        $state = InvestmentConfigurationBuilder::anInvestmentConfiguration()->create()->state();

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        (new ReflectionProperty($state, 'orderAmount'))->setValue($state, OrderAmount::of('1.00', 'EUR'));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function immutableTypes(): iterable
    {
        yield 'state DTO' => [InvestmentConfigurationStateDto::class];
        yield 'created event' => [InvestmentConfigurationCreated::class];
        yield 'identity' => [InvestmentConfigurationId::class];
        yield 'reference' => [InvestmentConfigurationRef::class];
        yield 'currency' => [InvestmentConfigurationCurrency::class];
        yield 'brokerage account' => [BrokerageAccount::class];
        yield 'order amount' => [OrderAmount::class];
        yield 'limit price tolerance' => [LimitPriceTolerance::class];
        yield 'analytics application' => [AnalyticsApplicationId::class];
        yield 'authorized analytics applications' => [AuthorizedAnalyticsApplications::class];
    }

    /**
     * The values these types hold are themselves immutable: Brick Money, BigDecimal, Currency, strings and lists of value objects.
     *
     * @param  class-string  $type
     */
    #[DataProvider('immutableTypes')]
    public function test_the_state_dto_and_value_objects_are_readonly(string $type): void
    {
        $this->assertTrue((new ReflectionClass($type))->isReadOnly(), $type.' must be a readonly class.');
    }
}
