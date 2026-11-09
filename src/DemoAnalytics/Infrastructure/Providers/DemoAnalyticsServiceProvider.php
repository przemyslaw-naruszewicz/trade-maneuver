<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Infrastructure\Providers;

use Carbon\FactoryImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use TradeManeuver\DemoAnalytics\Application\Port\SignalOutcomeQueueInterface;
use TradeManeuver\DemoAnalytics\Application\Port\TradeSignalPublisherInterface;
use TradeManeuver\DemoAnalytics\Application\SignalOutcome\Command\ReadSignalOutcomes\ReadSignalOutcomesCommandHandler;
use TradeManeuver\DemoAnalytics\Application\SignalOutcome\Command\ReadSignalOutcomes\SignalOutcomeMessageReader;
use TradeManeuver\DemoAnalytics\Application\TradeSignal\Command\PublishTradeSignal\PublishTradeSignalCommandHandler;
use TradeManeuver\DemoAnalytics\Application\TradeSignal\Command\PublishTradeSignal\TradeSignalMessageTranslator;
use TradeManeuver\DemoAnalytics\Infrastructure\Messaging\RabbitMq\RabbitMqSignalOutcomeQueue;
use TradeManeuver\DemoAnalytics\Infrastructure\Messaging\RabbitMq\RabbitMqTradeSignalPublisher;
use TradeManeuver\DemoAnalytics\Ui\ConsoleCommands\PublishTradeSignalCommand;
use TradeManeuver\DemoAnalytics\Ui\ConsoleCommands\ReadSignalOutcomesCommand;

/**
 * Wires the demo analytics application with its own RabbitMQ connection settings, apart from Trade Maneuver's.
 */
final class DemoAnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Resources/config.php', 'demo_analytics');

        $this->app->bind(
            TradeSignalPublisherInterface::class,
            fn ($app): RabbitMqTradeSignalPublisher => new RabbitMqTradeSignalPublisher(self::connectionConfig($app->make('config'))),
        );
        $this->app->bind(
            SignalOutcomeQueueInterface::class,
            fn ($app): RabbitMqSignalOutcomeQueue => new RabbitMqSignalOutcomeQueue(
                self::connectionConfig($app->make('config')),
                (string) $app->make('config')->get('demo_analytics.outcome_queue'),
            ),
        );
        $this->app->bind(
            PublishTradeSignalCommandHandler::class,
            fn ($app): PublishTradeSignalCommandHandler => new PublishTradeSignalCommandHandler(
                $app->make(TradeSignalPublisherInterface::class),
                new TradeSignalMessageTranslator((string) $app->make('config')->get('demo_analytics.source')),
                FactoryImmutable::getDefaultInstance(),
            ),
        );
        $this->app->bind(
            ReadSignalOutcomesCommandHandler::class,
            fn ($app): ReadSignalOutcomesCommandHandler => new ReadSignalOutcomesCommandHandler(
                $app->make(SignalOutcomeQueueInterface::class),
                new SignalOutcomeMessageReader,
            ),
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PublishTradeSignalCommand::class, ReadSignalOutcomesCommand::class]);
        }
    }

    private static function connectionConfig(Repository $config): AMQPConnectionConfig
    {
        $connection = new AMQPConnectionConfig;
        $connection->setHost((string) $config->get('demo_analytics.rabbitmq.host'));
        $connection->setPort((int) $config->get('demo_analytics.rabbitmq.port'));
        $connection->setVhost((string) $config->get('demo_analytics.rabbitmq.vhost'));
        $connection->setUser((string) $config->get('demo_analytics.rabbitmq.user'));
        $connection->setPassword((string) $config->get('demo_analytics.rabbitmq.password'));

        return $connection;
    }
}
