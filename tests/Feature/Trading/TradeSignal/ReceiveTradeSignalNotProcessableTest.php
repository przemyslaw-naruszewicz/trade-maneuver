<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\TradeSignal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Tests\Support\Trading\TradeSignalMessageBuilder;
use Tests\TestCase;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommand;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\ReceiveTradeSignalCommandHandler;
use TradeManeuver\Trading\Application\TradeSignal\Command\ReceiveTradeSignal\TradeSignalIntakeResult;

/**
 * A message that cannot be correlated or routed gets no signal outcome: the use case reports it as not processable,
 * audits it with the message metadata, and logs a warning.
 */
final class ReceiveTradeSignalNotProcessableTest extends TestCase
{
    use RefreshDatabase;

    private const string PUBLISHER = 'demo-analytics';

    private MockInterface $log;

    protected function setUp(): void
    {
        parent::setUp();

        config(['trading.analytics_applications' => [['id' => 'demo-analytics', 'rabbitmq_user' => self::PUBLISHER]]]);
        $this->log = Log::spy();
    }

    public function test_a_conflicting_duplicate_is_not_processable_and_audited_as_a_conflict(): void
    {
        $id = TradeSignalMessageBuilder::idFor('purchase');
        $this->receive(TradeSignalMessageBuilder::aTradeSignal($id)->with('confidence', '1.5')->body(), self::PUBLISHER);
        $conflictingBody = TradeSignalMessageBuilder::aTradeSignal($id)->with('confidence', '0.40')->body();

        $result = $this->receive($conflictingBody, self::PUBLISHER);

        $this->assertSame(TradeSignalIntakeResult::NotProcessable, $result);
        $this->assertSame(1, DB::table('trading_outbox_messages')->count(), 'The conflicting duplicate must not add an outcome.');
        $this->assertSame(1, DB::table('trading_trade_signals')->count());
        $audit = $this->soleAuditRecord('conflicting_duplicate');
        $this->assertSame($id, $audit['entity_id']);
        $this->assertSame('analytics-application:demo-analytics', $audit['actor']);
        $this->assertSame(hash('sha256', $conflictingBody), $audit['values']['fingerprint']);
        $this->assertWarningLogged('A trade signal message reuses an id with different content and is set aside.', $id);
    }

    public function test_a_message_without_an_id_is_not_processable_and_audited(): void
    {
        $body = TradeSignalMessageBuilder::aTradeSignal('')->without('id')->body();

        $this->assertSetAside($body, self::PUBLISHER, '', 'The message has no valid id.');
    }

    public function test_a_message_from_an_unknown_source_is_not_processable_and_audited(): void
    {
        $id = TradeSignalMessageBuilder::idFor('purchase');
        $body = TradeSignalMessageBuilder::aTradeSignal($id, 'demo-unknown')->body();

        $this->assertSetAside($body, self::PUBLISHER, $id, 'The source is not a known analytics application.');
    }

    public function test_a_message_whose_source_does_not_belong_to_the_publisher_is_not_processable_and_audited(): void
    {
        $id = TradeSignalMessageBuilder::idFor('purchase');
        $body = TradeSignalMessageBuilder::aTradeSignal($id)->body();

        $this->assertSetAside($body, 'demo-research', $id, 'The publisher identity is not the one registered for the source.');
    }

    private function assertSetAside(string $body, ?string $publisherIdentity, string $id, string $reason): void
    {
        $result = $this->receive($body, $publisherIdentity);

        $this->assertSame(TradeSignalIntakeResult::NotProcessable, $result);
        $this->assertDatabaseEmpty('trading_outbox_messages');
        $this->assertDatabaseEmpty('trading_trade_signals');
        $this->assertDatabaseEmpty('trading_inbox_entries');
        $audit = $this->soleAuditRecord('set_aside');
        $this->assertSame($id, $audit['entity_id']);
        $this->assertSame('publisher:'.$publisherIdentity, $audit['actor']);
        $this->assertSame($reason, $audit['values']['reason']);
        $this->assertSame((string) $publisherIdentity, $audit['values']['publisherIdentity']);
        $this->assertSame(hash('sha256', $body), $audit['values']['fingerprint']);
        $this->assertWarningLogged('A trade signal message cannot be processed and is set aside.', $id);
    }

    private function receive(string $body, ?string $publisherIdentity): TradeSignalIntakeResult
    {
        return $this->app->make(ReceiveTradeSignalCommandHandler::class)->handle(new ReceiveTradeSignalCommand($body, $publisherIdentity));
    }

    /**
     * @return array{actor: string, entity_id: string, values: array<string, mixed>}
     */
    private function soleAuditRecord(string $action): array
    {
        $records = DB::table('trading_audit_records')->where('action', $action)->get();
        $this->assertCount(1, $records, sprintf('Expected one "%s" audit record.', $action));
        $record = $records->sole();

        return [
            'actor' => (string) $record->actor,
            'entity_id' => (string) $record->entity_id,
            'values' => json_decode((string) $record->values, true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    private function assertWarningLogged(string $message, string $id): void
    {
        $this->log->shouldHaveReceived('warning')
            ->once()
            ->with($message, Mockery::on(static fn (array $context): bool => $context['id'] === $id));
    }
}
