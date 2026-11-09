<?php

declare(strict_types=1);

namespace Tests\Support\Trading;

use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Assert;

/**
 * Starts the long-running Trading processes as separate Artisan processes, with their own database and RabbitMQ
 * connections to the test database and the test vhost.
 */
trait TradingProcesses
{
    private const int WAIT_ATTEMPTS = 200;

    protected function startTradingProcess(string $command): InvokedProcess
    {
        return Process::path(base_path())
            ->env([
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => (string) DB::connection()->getDatabaseName(),
                'RABBITMQ_VHOST' => (string) config('trading.rabbitmq.vhost'),
                'TRADING_ANALYTICS_APPLICATIONS' => 'demo-analytics:demo-analytics,demo-research:demo-research',
            ])
            ->timeout(60)
            ->start([PHP_BINARY, 'artisan', $command]);
    }

    /**
     * Waits until the condition holds, so the test fails when the state it claims never happens.
     *
     * @param  callable(): bool  $condition
     */
    protected function waitUntil(callable $condition, string $description): void
    {
        for ($attempt = 0; $attempt < self::WAIT_ATTEMPTS; $attempt++) {
            if ($condition()) {
                return;
            }
            usleep(50_000);
        }
        Assert::fail('This did not happen in time: '.$description);
    }

    protected function waitForExit(InvokedProcess $process): int
    {
        $this->waitUntil(static fn (): bool => ! $process->running(), 'the process exits');

        return (int) $process->wait()->exitCode();
    }
}
