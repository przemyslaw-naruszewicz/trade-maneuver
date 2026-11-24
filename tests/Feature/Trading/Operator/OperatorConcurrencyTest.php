<?php

declare(strict_types=1);

namespace Tests\Feature\Trading\Operator;

use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\Feature\Trading\Operator\Support\OperatorAccounts;
use Tests\TestCase;
use TradeManeuver\Trading\Application\Operator\Command\ConfirmOperatorTotpCode\ConfirmOperatorTotpCodeCommand;
use TradeManeuver\Trading\Application\Operator\Command\ConfirmOperatorTotpCode\ConfirmOperatorTotpCodeCommandHandler;
use TradeManeuver\Trading\Application\Operator\Command\CreateOperator\CreateOperatorCommand;
use TradeManeuver\Trading\Application\Operator\Command\CreateOperator\CreateOperatorCommandHandler;

/**
 * Two connections run an operator use case at the same time. The test's connection runs it inside an open
 * transaction; a separate process with its own connection then runs it again and waits for that transaction.
 */
final class OperatorConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use OperatorAccounts;

    private const int WAIT_ATTEMPTS = 100;

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        DB::unprepared('TRUNCATE trading_operators');

        parent::tearDown();
    }

    public function test_a_code_confirmed_concurrently_on_two_connections_is_accepted_once(): void
    {
        $this->createEnrolledOperator();
        $operatorId = $this->operatorModel()->id;
        $code = $this->currentTotpCode();

        DB::beginTransaction();
        $this->app->make(ConfirmOperatorTotpCodeCommandHandler::class)->handle(new ConfirmOperatorTotpCodeCommand($operatorId, $code));

        $second = $this->startUseCase(['confirm', $operatorId, $code]);
        $secondWaited = $this->waitUntilWaitingOrFinished($second);
        DB::commit();
        $output = $second->wait()->output();

        $this->assertSame('InvalidTotpCodeException', $output, $second->latestErrorOutput());
        $this->assertTrue($secondWaited, 'The second connection was expected to wait for the lock on the operator account.');
    }

    public function test_operator_accounts_created_concurrently_on_two_connections_leave_exactly_one(): void
    {
        DB::beginTransaction();
        $this->app->make(CreateOperatorCommandHandler::class)
            ->handle(new CreateOperatorCommand(self::OPERATOR_EMAIL, self::OPERATOR_PASSWORD));

        $second = $this->startUseCase(['create', 'second-operator@example.test']);
        $secondWaited = $this->waitUntilWaitingOrFinished($second);
        DB::commit();
        $output = $second->wait()->output();

        $this->assertSame([self::OPERATOR_EMAIL], DB::table('trading_operators')->pluck('email')->all());
        $this->assertSame('OperatorAlreadyExistsException', $output, $second->latestErrorOutput());
        $this->assertTrue($secondWaited, 'The second connection was expected to wait for the first one on the single-row index.');
    }

    /**
     * @param  list<string>  $arguments
     */
    private function startUseCase(array $arguments): InvokedProcess
    {
        return Process::path(base_path())
            ->env([
                'APP_ENV' => 'testing',
                'APP_KEY' => (string) config('app.key'),
                'DB_CONNECTION' => 'pgsql',
                'DB_DATABASE' => (string) DB::connection()->getDatabaseName(),
            ])
            ->timeout(60)
            ->start([PHP_BINARY, 'tests/Feature/Trading/Operator/Support/operator-use-case.php', ...$arguments]);
    }

    /**
     * @return bool whether the process waits for a lock that another transaction holds
     */
    private function waitUntilWaitingOrFinished(InvokedProcess $process): bool
    {
        for ($attempt = 0; $attempt < self::WAIT_ATTEMPTS; $attempt++) {
            if ((int) DB::scalar("SELECT count(*) FROM pg_locks WHERE locktype = 'transactionid' AND NOT granted") > 0) {
                return true;
            }
            if (! $process->running()) {
                return false;
            }
            usleep(50_000);
        }

        return false;
    }
}
