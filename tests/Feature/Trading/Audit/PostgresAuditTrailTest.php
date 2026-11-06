<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Audit;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use TradeManeuver\Trading\Application\Audit\AuditRecordDto;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;

final class PostgresAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(AuditTrailInterface::class)->record(new AuditRecordDto(
            'console:trading:create-investment-configuration',
            CarbonImmutable::parse('2026-11-06T10:15:30.123456Z'),
            'investment_configuration',
            '019a5a6e-0000-7000-8000-000000000001',
            'created',
            ['orderAmount' => '200.00'],
        ));
    }

    public function test_an_audit_record_cannot_be_updated(): void
    {
        $this->assertWriteRejected(fn () => DB::table('trading_audit_records')->update(['action' => 'changed']));
    }

    public function test_an_audit_record_cannot_be_deleted(): void
    {
        $this->assertWriteRejected(fn () => DB::table('trading_audit_records')->delete());
    }

    private function assertWriteRejected(callable $write): void
    {
        try {
            DB::transaction($write(...));
            $this->fail('The audit trail accepted a change to a stored record.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('The audit trail is append-only.', $exception->getMessage());
        }

        $this->assertSame('created', DB::table('trading_audit_records')->sole()->action);
    }
}
