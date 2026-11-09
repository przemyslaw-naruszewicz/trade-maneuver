<?php

declare(strict_types=1);

namespace Tests\Support\Trading;

use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Starts the long-running Trading processes as separate Artisan processes, with their own database and RabbitMQ
 * connections to the test database and the test vhost.
 */
trait TradingProcesses
{
    /** How often retry() checks a condition that another process brings about, and how long it waits in between. */
    private const int WAIT_ATTEMPTS = 200;

    private const int WAIT_PAUSE_MILLISECONDS = 50;

    /** @var list<InvokedProcess> */
    private array $tradingProcesses = [];

    protected function startTradingProcess(string $command): InvokedProcess
    {
        $process = Process::path(base_path())
            ->env([
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => (string) DB::connection()->getDatabaseName(),
                'RABBITMQ_VHOST' => (string) config('trading.rabbitmq.vhost'),
                'TRADING_ANALYTICS_APPLICATIONS' => 'demo-analytics:demo-analytics,demo-research:demo-research',
            ])
            ->timeout(60)
            ->start([PHP_BINARY, 'artisan', $command]);
        $this->tradingProcesses[] = $process;

        return $process;
    }

    /**
     * Kills the processes a failed test left running, so they cannot take messages from the next test.
     */
    protected function killTradingProcesses(): void
    {
        foreach ($this->tradingProcesses as $process) {
            if ($process->running()) {
                $process->signal(SIGKILL);
            }
        }
        $this->tradingProcesses = [];
    }
}
