<?php

declare(strict_types=1);

namespace Tests\Unit\Trading\InvestmentConfiguration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvalidInvestmentConfigurationRefException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;

final class InvestmentConfigurationRefTest extends TestCase
{
    public function test_a_non_empty_reference_is_kept_as_given(): void
    {
        $this->assertSame('demo-investment', (new InvestmentConfigurationRef('demo-investment'))->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emptyReferences(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => [" \t"];
    }

    #[DataProvider('emptyReferences')]
    public function test_an_empty_reference_is_rejected(string $reference): void
    {
        $this->expectException(InvalidInvestmentConfigurationRefException::class);
        $this->expectExceptionMessage('The investment configuration reference must not be empty.');

        new InvestmentConfigurationRef($reference);
    }
}
