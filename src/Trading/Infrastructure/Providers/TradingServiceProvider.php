<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Providers;

use Carbon\FactoryImmutable;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use Psr\Clock\ClockInterface;
use TradeManeuver\Trading\Application\Port\AnalyticsApplicationRegistryInterface;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\BrokerGatewayInterface;
use TradeManeuver\Trading\Application\Port\InboxInterface;
use TradeManeuver\Trading\Application\Port\IntegrationEventPublisherInterface;
use TradeManeuver\Trading\Application\Port\PasswordHasherInterface;
use TradeManeuver\Trading\Application\Port\SignalOutcomeRecordingQueueInterface;
use TradeManeuver\Trading\Application\Port\TotpAuthenticatorInterface;
use TradeManeuver\Trading\Application\Port\TradeSignalEvaluationQueueInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Application\Workflow\TradeSignalEvaluation\TradeSignalEvaluationStoreInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\Operator\Repository\OperatorRepositoryInterface;
use TradeManeuver\Trading\Domain\Order\Repository\OrderRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Infrastructure\AnalyticsApplication\ConfiguredAnalyticsApplicationRegistry;
use TradeManeuver\Trading\Infrastructure\Audit\PostgresAuditTrail;
use TradeManeuver\Trading\Infrastructure\Broker\SimulatedBroker;
use TradeManeuver\Trading\Infrastructure\InvestmentConfiguration\InvestmentConfigurationRepository;
use TradeManeuver\Trading\Infrastructure\Messaging\Inbox\PostgresInbox;
use TradeManeuver\Trading\Infrastructure\Messaging\Jobs\QueuedSignalOutcomeRecording;
use TradeManeuver\Trading\Infrastructure\Messaging\Jobs\QueuedTradeSignalEvaluation;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\OutboxIntegrationEventPublisher;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\SignalOutcomePublisherInterface;
use TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq\RabbitMqSignalOutcomePublisher;
use TradeManeuver\Trading\Infrastructure\Operator\Eloquent\OperatorModel;
use TradeManeuver\Trading\Infrastructure\Operator\Google2FaTotpAuthenticator;
use TradeManeuver\Trading\Infrastructure\Operator\LaravelPasswordHasher;
use TradeManeuver\Trading\Infrastructure\Operator\OperatorRepository;
use TradeManeuver\Trading\Infrastructure\Order\OrderRepository;
use TradeManeuver\Trading\Infrastructure\Persistence\LaravelTransactionManager;
use TradeManeuver\Trading\Infrastructure\TradeSignal\TradeSignalRepository;
use TradeManeuver\Trading\Infrastructure\Workflow\TradeSignalEvaluationStore;
use TradeManeuver\Trading\Ui\ConsoleCommands\CreateInvestmentConfigurationCommand;
use TradeManeuver\Trading\Ui\ConsoleCommands\CreateOperatorCommand;
use TradeManeuver\Trading\Ui\ConsoleCommands\EnrolOperatorTotpCommand;
use TradeManeuver\Trading\Ui\ConsoleCommands\ResetOperatorPasswordCommand;
use TradeManeuver\Trading\Ui\Http\Web\OperatorLoginThrottle;
use TradeManeuver\Trading\Ui\Livewire\Orders;
use TradeManeuver\Trading\Ui\Livewire\OrdersAwaitingApprovalCount;
use TradeManeuver\Trading\Ui\Livewire\Start;
use TradeManeuver\Trading\Ui\Messaging\ConsumeTradeSignalsCommand;

final class TradingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Resources/config.php', 'trading');

        $this->app->bind(InvestmentConfigurationRepositoryInterface::class, InvestmentConfigurationRepository::class);
        $this->app->bind(TradeSignalRepositoryInterface::class, TradeSignalRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(OperatorRepositoryInterface::class, OperatorRepository::class);
        $this->app->bind(PasswordHasherInterface::class, LaravelPasswordHasher::class);
        $this->app->bind(TotpAuthenticatorInterface::class, Google2FaTotpAuthenticator::class);
        $this->app->bind(OperatorLoginThrottle::class, fn ($app): OperatorLoginThrottle => new OperatorLoginThrottle(
            $app->make(RateLimiter::class),
            (int) $app->make('config')->get('trading.operator_login.max_failed_attempts_per_minute'),
        ));
        $this->app->bind(ClockInterface::class, fn (): FactoryImmutable => FactoryImmutable::getDefaultInstance());
        $this->app->bind(
            AnalyticsApplicationRegistryInterface::class,
            fn ($app): ConfiguredAnalyticsApplicationRegistry => new ConfiguredAnalyticsApplicationRegistry(
                (array) $app->make('config')->get('trading.analytics_applications', []),
            ),
        );
        $this->app->bind(
            TransactionManagerInterface::class,
            fn ($app): LaravelTransactionManager => new LaravelTransactionManager($app->make(ConnectionInterface::class)),
        );
        $this->app->bind(
            AuditTrailInterface::class,
            fn ($app): PostgresAuditTrail => new PostgresAuditTrail($app->make(ConnectionInterface::class)),
        );
        $this->app->bind(
            InboxInterface::class,
            fn ($app): PostgresInbox => new PostgresInbox($app->make(ConnectionInterface::class)),
        );
        $this->app->bind(
            IntegrationEventPublisherInterface::class,
            fn ($app): OutboxIntegrationEventPublisher => new OutboxIntegrationEventPublisher(
                $app->make(ConnectionInterface::class),
                $app->make(ClockInterface::class),
                $app->make(Dispatcher::class),
            ),
        );
        $this->app->bind(TradeSignalEvaluationQueueInterface::class, QueuedTradeSignalEvaluation::class);
        $this->app->bind(SignalOutcomeRecordingQueueInterface::class, QueuedSignalOutcomeRecording::class);
        $this->app->bind(
            TradeSignalEvaluationStoreInterface::class,
            fn ($app): TradeSignalEvaluationStore => new TradeSignalEvaluationStore($app->make(ConnectionInterface::class)),
        );
        // The simulated broker is the only broker outside tests, so nothing chooses between adapters.
        $this->app->bind(
            BrokerGatewayInterface::class,
            fn ($app): SimulatedBroker => new SimulatedBroker(
                (array) $app->make('config')->get('trading.simulated_broker.instruments', []),
            ),
        );
        $this->app->bind(AMQPConnectionConfig::class, function ($app): AMQPConnectionConfig {
            $config = $app->make('config');
            $connection = new AMQPConnectionConfig;
            $connection->setHost((string) $config->get('trading.rabbitmq.host'));
            $connection->setPort((int) $config->get('trading.rabbitmq.port'));
            $connection->setVhost((string) $config->get('trading.rabbitmq.vhost'));
            $connection->setUser((string) $config->get('trading.rabbitmq.user'));
            $connection->setPassword((string) $config->get('trading.rabbitmq.password'));

            return $connection;
        });
        $this->app->singleton(SignalOutcomePublisherInterface::class, RabbitMqSignalOutcomePublisher::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/Resources/Migrations');
        $this->loadRoutesFrom(dirname(__DIR__, 2).'/Ui/Http/routes.php');
        Blade::anonymousComponentPath(resource_path('views/trading/components'), 'trading');
        Livewire::component('trading.start', Start::class);
        Livewire::component('trading.orders', Orders::class);
        Livewire::component('trading.orders-awaiting-approval-count', OrdersAwaitingApprovalCount::class);
        $this->app->make(AuthManager::class)->provider(
            'trading-operators',
            fn ($app): EloquentUserProvider => new EloquentUserProvider($app->make(Hasher::class), OperatorModel::class),
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateInvestmentConfigurationCommand::class,
                ConsumeTradeSignalsCommand::class,
                CreateOperatorCommand::class,
                ResetOperatorPasswordCommand::class,
                EnrolOperatorTotpCommand::class,
            ]);
        }
    }
}
