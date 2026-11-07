<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\TradeSignal;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Tests\TestCase;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommand;
use TradeManeuver\Trading\Application\InvestmentConfiguration\Command\CreateInvestmentConfiguration\CreateInvestmentConfigurationCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\TradeSignalIntakeResult;

/**
 * The records an accepted delivery commits: Behat covers the business flows, this test the exact stored values.
 */
final class ReceiveTradeSignalTest extends TestCase
{
    use RefreshDatabase;

    private const string RECEIVED_AT = '2026-11-07T09:30:00.250000Z';

    protected function setUp(): void
    {
        parent::setUp();

        config(['trading.analytics_applications' => [
            ['id' => 'demo-analytics', 'rabbitmq_user' => 'demo-analytics'],
            ['id' => 'demo-research', 'rabbitmq_user' => 'demo-research'],
        ]]);
        $this->app->make(CreateInvestmentConfigurationCommandHandler::class)->handle(new CreateInvestmentConfigurationCommand(
            'test',
            'demo-investment',
            'EUR',
            'DEMO-ACCOUNT-1',
            '200.00',
            'EUR',
            '1.50',
            ['demo-analytics'],
        ));
        $this->travelTo(CarbonImmutable::parse(self::RECEIVED_AT));
    }

    public function test_a_valid_trade_signal_is_stored_with_its_inbox_entry_and_audit_record(): void
    {
        $id = TradeSignalMessageBuilder::idFor('purchase');
        $body = TradeSignalMessageBuilder::aTradeSignal($id)->body();

        $this->assertSame(TradeSignalIntakeResult::Accepted, $this->receive($body, 'demo-analytics'));

        $inboxEntry = DB::table('trading_inbox_entries')->sole();
        $this->assertSame('trading.trade-signal-intake', $inboxEntry->consumer);
        $this->assertSame($id, $inboxEntry->message_id);
        $this->assertSame(hash('sha256', $body), $inboxEntry->fingerprint);
        $tradeSignal = DB::table('trading_trade_signals')->sole();
        $this->assertSame('demo-analytics', $tradeSignal->source);
        $this->assertSame('demo-investment', $tradeSignal->investment_configuration_ref);
        $this->assertNull($tradeSignal->outcome);
        $audit = DB::table('trading_audit_records')->where('entity_type', 'trade_signal')->sole();
        $this->assertSame('analytics-application:demo-analytics', $audit->actor);
        $this->assertSame('received', $audit->action);
        $this->assertSame($id, $audit->entity_id);
        // jsonb orders object keys by itself, so the comparison ignores key order.
        $this->assertEquals(
            ['id' => $id, 'source' => 'demo-analytics', 'type' => 'tm.trade-signal.v1', 'fingerprint' => hash('sha256', $body)],
            json_decode((string) $audit->values, true),
        );
        $this->assertDatabaseEmpty('trading_outbox_messages');
    }

    public function test_a_rejected_trade_signal_records_its_signal_outcome_in_the_outbox(): void
    {
        $id = TradeSignalMessageBuilder::idFor('purchase');
        $body = TradeSignalMessageBuilder::aTradeSignal($id)->with('confidence', '1.5')->without('instrument.symbol')->body();

        $this->assertSame(TradeSignalIntakeResult::Accepted, $this->receive($body, 'demo-analytics'));

        $tradeSignal = DB::table('trading_trade_signals')->sole();
        $outboxMessage = DB::table('trading_outbox_messages')->sole();
        $this->assertSame($tradeSignal->outcome_id, $outboxMessage->message_id);
        $this->assertSame('tm.signal-outcome.v1', $outboxMessage->message_type);
        $this->assertSame('demo-analytics', $outboxMessage->recipient);
        $this->assertNull($outboxMessage->sent_at);
        $violations = [
            ['field' => 'instrument.symbol', 'message' => 'The field is required.'],
            ['field' => 'confidence', 'message' => 'The confidence must be a decimal string from 0 to 1, such as 0.85.'],
        ];
        $this->assertEquals([
            'id' => $tradeSignal->outcome_id,
            'source' => 'trade-maneuver',
            'type' => 'tm.signal-outcome.v1',
            'time' => self::RECEIVED_AT,
            'signalId' => $id,
            'outcome' => 'SignalRejected',
            'violations' => $violations,
        ], json_decode((string) $outboxMessage->payload, true));
        $audit = DB::table('trading_audit_records')->where('entity_type', 'trade_signal')->sole();
        $this->assertSame('rejected', $audit->action);
        $values = json_decode((string) $audit->values, true);
        $this->assertIsArray($values);
        $this->assertSame($tradeSignal->outcome_id, $values['outcomeId']);
        $this->assertSame([
            'instrument.symbol: The field is required.',
            'confidence: The confidence must be a decimal string from 0 to 1, such as 0.85.',
        ], $values['violations']);
    }

    public function test_an_unauthorized_analytics_application_is_named_in_the_violation(): void
    {
        $body = TradeSignalMessageBuilder::aTradeSignal(TradeSignalMessageBuilder::idFor('purchase'), 'demo-research')->body();

        $this->assertSame(TradeSignalIntakeResult::Accepted, $this->receive($body, 'demo-research'));

        $payload = json_decode((string) DB::table('trading_outbox_messages')->sole()->payload, true);
        $this->assertIsArray($payload);
        $this->assertSame([['field' => 'source', 'message' => 'The analytics application is not authorized for the investment configuration.']], $payload['violations']);
        $this->assertSame('demo-research', DB::table('trading_outbox_messages')->sole()->recipient);
    }

    public function test_an_unknown_investment_configuration_is_named_in_the_violation(): void
    {
        $body = TradeSignalMessageBuilder::aTradeSignal(TradeSignalMessageBuilder::idFor('purchase'))->with('investmentConfigurationRef', 'demo-unknown')->body();

        $this->receive($body, 'demo-analytics');

        $payload = json_decode((string) DB::table('trading_outbox_messages')->sole()->payload, true);
        $this->assertIsArray($payload);
        $this->assertSame([['field' => 'investmentConfigurationRef', 'message' => 'The investment configuration is unknown.']], $payload['violations']);
    }

    public function test_a_duplicate_of_a_valid_trade_signal_is_audited_and_records_no_outcome(): void
    {
        $id = TradeSignalMessageBuilder::idFor('purchase');
        $body = TradeSignalMessageBuilder::aTradeSignal($id)->body();
        $this->receive($body, 'demo-analytics');

        $this->assertSame(TradeSignalIntakeResult::Accepted, $this->receive($body, 'demo-analytics'));

        $this->assertSame(1, DB::table('trading_trade_signals')->count());
        $this->assertDatabaseEmpty('trading_outbox_messages');
        $audit = DB::table('trading_audit_records')->where('action', 'duplicate_delivered')->sole();
        $this->assertSame($id, $audit->entity_id);
        $this->assertSame('analytics-application:demo-analytics', $audit->actor);
        $values = json_decode((string) $audit->values, true);
        $this->assertIsArray($values);
        $this->assertSame('', $values['outcomeId']);
    }

    public function test_a_duplicate_of_a_rejected_trade_signal_records_the_same_outcome_message_again(): void
    {
        $body = TradeSignalMessageBuilder::aTradeSignal(TradeSignalMessageBuilder::idFor('purchase'))->with('confidence', '1.5')->body();
        $this->receive($body, 'demo-analytics');
        $this->travelTo(CarbonImmutable::parse('2026-11-07T10:00:00Z'));

        $this->receive($body, 'demo-analytics');

        $outboxMessages = DB::table('trading_outbox_messages')->orderBy('id')->get();
        $this->assertCount(2, $outboxMessages);
        $this->assertSame($outboxMessages[0]->message_id, $outboxMessages[1]->message_id);
        $this->assertSame(json_decode((string) $outboxMessages[0]->payload, true), json_decode((string) $outboxMessages[1]->payload, true));
        $values = json_decode((string) DB::table('trading_audit_records')->where('action', 'duplicate_delivered')->sole()->values, true);
        $this->assertIsArray($values);
        $this->assertSame($outboxMessages[0]->message_id, $values['outcomeId']);
    }

    private function receive(string $body, ?string $publisherIdentity): TradeSignalIntakeResult
    {
        return $this->app->make(ReceiveTradeSignalCommandHandler::class)->handle(new ReceiveTradeSignalCommand($body, $publisherIdentity));
    }
}
