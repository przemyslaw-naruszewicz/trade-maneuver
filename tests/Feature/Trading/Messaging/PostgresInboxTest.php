<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Messaging;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use TradeManeuver\Trading\Application\Inbox\InboxDelivery;
use TradeManeuver\Trading\Application\Inbox\InboxEntryDto;
use TradeManeuver\Trading\Application\Port\InboxInterface;

final class PostgresInboxTest extends TestCase
{
    use RefreshDatabase;

    private const string MESSAGE_ID = '019a5a6e-0000-7000-8000-0000000000a1';

    public function test_the_first_delivery_is_recorded_and_later_ones_are_duplicates_or_conflicts_by_fingerprint(): void
    {
        $inbox = $this->app->make(InboxInterface::class);

        $this->assertSame(InboxDelivery::First, $inbox->register($this->entry('consumer-a', self::fingerprint('body-1'))));
        $this->assertSame(InboxDelivery::Duplicate, $inbox->register($this->entry('consumer-a', self::fingerprint('body-1'))));
        $this->assertSame(InboxDelivery::ConflictingDuplicate, $inbox->register($this->entry('consumer-a', self::fingerprint('body-2'))));

        $entry = DB::table('trading_inbox_entries')->sole();
        $this->assertSame(self::fingerprint('body-1'), $entry->fingerprint);
        $this->assertSame('2026-11-07 09:30:00.25+00', $entry->received_at);
    }

    public function test_entries_are_unique_per_consumer(): void
    {
        $inbox = $this->app->make(InboxInterface::class);

        $inbox->register($this->entry('consumer-a', self::fingerprint('body-1')));

        $this->assertSame(InboxDelivery::First, $inbox->register($this->entry('consumer-b', self::fingerprint('body-2'))));
        $this->assertSame(2, DB::table('trading_inbox_entries')->count());
    }

    private static function fingerprint(string $body): string
    {
        return hash('sha256', $body);
    }

    private function entry(string $consumer, string $fingerprint): InboxEntryDto
    {
        return new InboxEntryDto($consumer, self::MESSAGE_ID, $fingerprint, new DateTimeImmutable('2026-11-07T09:30:00.25Z'));
    }
}
