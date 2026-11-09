<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Providers;

use Carbon\FactoryImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\ServiceProvider;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use Psr\Clock\ClockInterface;
use TradeManeuver\Trading\Application\Port\AnalyticsApplicationRegistryInterface;
use TradeManeuver\Trading\Application\Port\AuditTrailInterface;
use TradeManeuver\Trading\Application\Port\InboxInterface;
use TradeManeuver\Trading\Application\Port\IntegrationEventPublisherInterface;
use TradeManeuver\Trading\Application\Port\TransactionManagerInterface;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\InvestmentConfigurationRepositoryInterface;
use TradeManeuver\Trading\Domain\TradeSignal\Repository\TradeSignalRepositoryInterface;
use TradeManeuver\Trading\Infrastructure\AnalyticsApplication\ConfiguredAnalyticsApplicationRegistry;
use TradeManeuver\Trading\Infrastructure\Audit\PostgresAuditTrail;
use TradeManeuver\Trading\Infrastructure\InvestmentConfiguration\InvestmentConfigurationRepository;
use TradeManeuver\Trading\Infrastructure\Messaging\Inbox\PostgresInbox;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\OutboxIntegrationEventPublisher;
use TradeManeuver\Trading\Infrastructure\Messaging\Outbox\SignalOutcomePublisherInterface;
use TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq\ConsumeTradeSignalsCommand;
use TradeManeuver\Trading\Infrastructure\Messaging\RabbitMq\RabbitMqSignalOutcomePublisher;
use TradeManeuver\Trading\Infrastructure\Persistence\LaravelTransactionManager;
use TradeManeuver\Trading\Infrastructure\TradeSignal\TradeSignalRepository;
use TradeManeuver\Trading\Ui\ConsoleCommands\CreateInvestmentConfigurationCommand;

final class TradingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Resources/config.php', 'trading');

        $this->app->bind(InvestmentConfigurationRepositoryInterface::class, InvestmentConfigurationRepository::class);
        $this->app->bind(TradeSignalRepositoryInterface::class, TradeSignalRepository::class);
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

        if ($this->app->runningInConsole()) {
            $this->commands([CreateInvestmentConfigurationCommand::class, ConsumeTradeSignalsCommand::class]);
        }
    }
}
