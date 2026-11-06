<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository;

use TradeManeuver\Trading\Domain\InvestmentConfiguration\Exception\InvestmentConfigurationAlreadyExistsException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\InvestmentConfiguration;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\Repository\Exception\InvestmentConfigurationDoesNotExistException;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationId;
use TradeManeuver\Trading\Domain\InvestmentConfiguration\ValueObject\InvestmentConfigurationRef;

interface InvestmentConfigurationRepositoryInterface
{
    public function nextIdentity(): InvestmentConfigurationId;

    public function exists(): bool;

    public function existsWithReference(InvestmentConfigurationRef $reference): bool;

    /**
     * @throws InvestmentConfigurationAlreadyExistsException
     */
    public function add(InvestmentConfiguration $investmentConfiguration): void;

    /**
     * @throws InvestmentConfigurationDoesNotExistException
     */
    public function get(InvestmentConfigurationId $id): InvestmentConfiguration;
}
