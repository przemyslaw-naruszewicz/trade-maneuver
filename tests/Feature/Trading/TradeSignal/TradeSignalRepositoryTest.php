<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\TradeSignal;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Unit\Trading\TradeSignal\Support\TradeSignalBuilder;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\AnalyticsApplicationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;
use TradeManeuver\Trading\Domain\TradeSignal\Enum\Direction;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\Exception\TradeSignalDoesNotExistException;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\TradeSignal;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Confidence;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Horizon;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Instrument;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\Mic;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\ReferencePrice;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalContent;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\TradeSignalId;
use TradeManeuver\Trading\Domain\TradeSignal\ValueObject\UtcTimestamp;

final class TradeSignalRepositoryTest extends TestCase
{
    use RefreshDatabase;

    // Twenty significant digits: a conversion through float would change the last ones.
    private const string REFERENCE_PRICE = '123456789012.34567891';

    public function test_a_received_trade_signal_round_trips_with_its_exact_content(): void
    {
        $repository = $this->app->make(TradeSignalRepositoryInterface::class);
        $received = TradeSignalBuilder::received();

        $repository->add($received);
        $reloaded = $repository->get($received->id());

        $this->assertEquals($received->state(), $reloaded->state());
        $this->assertSame([], $reloaded->releaseEvents());
        $this->assertTrue($reloaded->awaitsEvaluation());
        $row = DB::table('trading_trade_signals')->sole();
        $this->assertSame('0.85', $row->confidence);
        $this->assertSame('12.50', $row->reference_price_amount);
        $this->assertSame('1.5', $row->expected_move_min);
        $this->assertSame('4.0', $row->expected_move_max);
        $this->assertSame('2026-11-20', $row->horizon_at);
    }

    public function test_decimals_keep_every_digit_and_optional_content_stays_empty(): void
    {
        $repository = $this->app->make(TradeSignalRepositoryInterface::class);
        $received = TradeSignal::receive(
            new TradeSignalId(TradeSignalBuilder::ID),
            new AnalyticsApplicationId('demo-analytics'),
            new TradeSignalContent(
                UtcTimestamp::fromString('2026-11-07T09:00:00.123456Z'),
                new InvestmentConfigurationRef('demo-investment'),
                new Instrument('DEMO_A', new Mic('DEMO')),
                Direction::Short,
                Confidence::of('0.123456789012345678901234567890'),
                Horizon::of(null, '2026-11-20'),
                null,
                ReferencePrice::of(self::REFERENCE_PRICE, 'EUR', '2026-11-07T08:59:00Z'),
                UtcTimestamp::fromString('2026-11-10T16:00:00Z'),
            ),
            new \DateTimeImmutable(TradeSignalBuilder::RECEIVED_AT),
        );

        $repository->add($received);
        $reloaded = $repository->get($received->id());

        $this->assertEquals($received->state(), $reloaded->state());
        $row = DB::table('trading_trade_signals')->sole();
        $this->assertSame(self::REFERENCE_PRICE, $row->reference_price_amount);
        $this->assertNull($row->expected_move_min);
        $this->assertNull($row->horizon_sessions);
    }

    public function test_a_rejected_trade_signal_round_trips_with_its_signal_outcome(): void
    {
        $repository = $this->app->make(TradeSignalRepositoryInterface::class);
        $rejected = TradeSignalBuilder::rejected();

        $repository->add($rejected);
        $reloaded = $repository->get($rejected->id());

        $this->assertEquals($rejected->state(), $reloaded->state());
        $this->assertEquals($rejected->outcome(), $reloaded->outcome());
        $this->assertSame([], $reloaded->releaseEvents());
        $row = DB::table('trading_trade_signals')->sole();
        $this->assertSame('SignalRejected', $row->outcome);
        $this->assertNull($row->issued_at);
    }

    public function test_a_stored_outcome_of_an_unknown_type_is_refused(): void
    {
        $repository = $this->app->make(TradeSignalRepositoryInterface::class);
        $repository->add(TradeSignalBuilder::rejected());
        DB::table('trading_trade_signals')->update(['outcome' => 'OrderFilled']);

        $this->expectException(\ValueError::class);

        $repository->get(new TradeSignalId(TradeSignalBuilder::ID));
    }

    public function test_the_database_refuses_a_second_trade_signal_with_the_same_id(): void
    {
        $repository = $this->app->make(TradeSignalRepositoryInterface::class);
        $repository->add(TradeSignalBuilder::received());

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->add(TradeSignalBuilder::rejected());
    }

    public function test_getting_an_unknown_trade_signal_fails(): void
    {
        $this->expectExceptionObject(TradeSignalDoesNotExistException::withId(new TradeSignalId(TradeSignalBuilder::ID)));

        $this->app->make(TradeSignalRepositoryInterface::class)->get(new TradeSignalId(TradeSignalBuilder::ID));
    }

    public function test_outcome_identities_are_new_uuids(): void
    {
        $repository = $this->app->make(TradeSignalRepositoryInterface::class);

        $this->assertNotSame($repository->nextOutcomeIdentity()->toString(), $repository->nextOutcomeIdentity()->toString());
    }
}
