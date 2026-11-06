<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject;

/**
 * The set of analytics applications that may address trade signals to an investment configuration.
 * An empty set means every trade signal addressing it is rejected.
 */
final readonly class AuthorizedAnalyticsApplications
{
    /**
     * @param  list<AnalyticsApplicationId>  $analyticsApplications
     */
    private function __construct(private array $analyticsApplications) {}

    public static function of(AnalyticsApplicationId ...$analyticsApplications): self
    {
        $byIdentifier = [];
        foreach ($analyticsApplications as $analyticsApplication) {
            $byIdentifier[$analyticsApplication->toString()] = $analyticsApplication;
        }
        ksort($byIdentifier, SORT_STRING);

        return new self(array_values($byIdentifier));
    }

    /**
     * @return list<AnalyticsApplicationId>
     */
    public function all(): array
    {
        return $this->analyticsApplications;
    }

    /**
     * @return list<string>
     */
    public function identifiers(): array
    {
        $identifiers = [];
        foreach ($this->analyticsApplications as $analyticsApplication) {
            $identifiers[] = $analyticsApplication->toString();
        }

        return $identifiers;
    }
}
