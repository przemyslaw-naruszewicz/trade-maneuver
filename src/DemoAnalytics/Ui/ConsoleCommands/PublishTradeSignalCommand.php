<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Ui\ConsoleCommands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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
    public function handle(): int
    {
        $this->info(sprintf('Published trade signal %s.', Str::uuid7()->toString()));

        return self::SUCCESS;
    }
}
