<?php

declare(strict_types=1);

namespace Tests\Unit\DemoAnalytics;

use PHPUnit\Framework\TestCase;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\Exception\InvalidTradeSignalException;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\DemoInstrument;
use TradeManeuver\DemoAnalytics\Domain\TradeSignal\ValueObject\InvestmentConfigurationRef;

/**
 * Cases the console command cannot pass: an option value with a line break, and a blank reference, which the command
 * refuses before it reaches the domain.
 */
final class TradeSignalValueObjectsTest extends TestCase
{
    public function test_a_symbol_followed_by_a_line_break_is_refused(): void
    {
        $this->expectException(InvalidTradeSignalException::class);

        new DemoInstrument("DEMO_A\n");
    }

    public function test_a_blank_investment_configuration_reference_is_refused(): void
    {
        $this->expectException(InvalidTradeSignalException::class);
        $this->expectExceptionMessage('The investment configuration reference must not be empty.');

        new InvestmentConfigurationRef(' ');
    }
}
