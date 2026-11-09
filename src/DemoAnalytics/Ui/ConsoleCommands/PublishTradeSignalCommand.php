<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use TradeManeuver\DemoAnalytics\Application\TradeSignal\Command\PublishTradeSignal\PublishTradeSignalCommand as PublishTradeSignal;
use TradeManeuver\DemoAnalytics\Application\TradeSignal\Command\PublishTradeSignal\PublishTradeSignalCommandHandler;
use TradeManeuver\DemoAnalytics\Application\TradeSignal\Exception\TradeSignalNotPublishedException;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;

/**
 * Publishes one fictional trade signal with invented values and prints its `id`. Invalid values are refused before
 * anything is published.
 */
#[Signature('demo-analytics:publish-trade-signal
    {--symbol=DEMO_A : The fictional instrument symbol, starting with DEMO_}
    {--direction=long : long or short}
    {--confidence=0.75 : A decimal from 0 to 1}
    {--horizon-sessions=5 : The horizon in trading sessions}
    {--expected-move-min=1.0 : The expected move minimum as a decimal percentage}
    {--expected-move-max=3.0 : The expected move maximum as a decimal percentage}
    {--reference-price=12.50 : The invented reference price as a decimal string}
    {--currency=EUR : The reference price currency}
    {--valid-until= : The end of signal validity in UTC, such as 2026-11-12T16:00:00Z; by default three days from now}
    {--investment-configuration-ref= : The investment configuration reference; by default DEMO_ANALYTICS_INVESTMENT_CONFIGURATION_REF}
    {--twice : Publish the same message twice}')]
#[Description('Publish a fictional trade signal as the demo analytics application')]
final class PublishTradeSignalCommand extends Command
{
    public function handle(PublishTradeSignalCommandHandler $handler): int
    {
        $investmentConfigurationRef = $this->stringOption('investment-configuration-ref') ?? (string) config('demo_analytics.investment_configuration_ref');
        if (trim($investmentConfigurationRef) === '') {
            $this->error('The investment configuration reference must not be empty; set --investment-configuration-ref or DEMO_ANALYTICS_INVESTMENT_CONFIGURATION_REF.');

            return self::INVALID;
        }
        $id = Str::uuid7()->toString();

        try {
            $handler->handle(new PublishTradeSignal(
                $id,
                $investmentConfigurationRef,
                (string) $this->stringOption('symbol'),
                (string) $this->stringOption('direction'),
                (string) $this->stringOption('confidence'),
                (string) $this->stringOption('horizon-sessions'),
                (string) $this->stringOption('expected-move-min'),
                (string) $this->stringOption('expected-move-max'),
                (string) $this->stringOption('reference-price'),
                (string) $this->stringOption('currency'),
                $this->stringOption('valid-until'),
                $this->option('twice') === true,
            ));
        } catch (InvalidTradeSignalException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        } catch (TradeSignalNotPublishedException $exception) {
            $this->error('The trade signal was not published: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Published trade signal %s.', $id));

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }
}
